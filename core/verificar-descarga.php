<?php
// core/verificar-descarga.php

header('Content-Type: application/json; charset=utf-8');

$autoloadPrimary = __DIR__ . '/../vendor/autoload.php';
$autoloadFallback = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPrimary)) {
    require_once $autoloadPrimary;
} elseif (file_exists($autoloadFallback)) {
    require_once $autoloadFallback;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sin autoload composer']);
    exit;
}

// Iniciar sesión para acceder con Token y FIEL
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;
use PhpCfdi\SatWsDescargaMasiva\Shared\Token;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use GuzzleHttp\Client;


function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

//Crea una instancia de Service, inyectando el Token de la sesión si existe.
function getServiceInstance(string $rfc): ?Service
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['sat_data'][$rfc]['fiel_credentials'])) {
        return null;
    }
    $fielData = $_SESSION['sat_data'][$rfc]['fiel_credentials'];
    $tokenData = $_SESSION['sat_data'][$rfc]['token_data'] ?? null;
    $token = null;

    try {
        // Crear Fiel
        $fiel = Fiel::create($fielData['cer_content'], $fielData['key_content'], $fielData['passphrase']);
        if (!$fiel->isValid()) {
            return null;
        }
    } catch (\Throwable $e) {
        error_log("Error al crear la FIEL para $rfc: " . $e->getMessage());
        return null;
    }

    if ($tokenData) {
        $token = new Token(
            DateTime::create($tokenData['created']),
            DateTime::create($tokenData['expires']),
            $tokenData['value']
        );
    }
    
    // Crear Service
    $webClient = new GuzzleWebClient(new Client(['timeout' => 90]));
    $requestBuilder = new FielRequestBuilder($fiel);
    return new Service($requestBuilder, $webClient, $token);
}

//Guarda el Token de autenticación en la sesión
function saveTokenToSession(string $rfc, Token $token): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['sat_data'][$rfc]['token_data'] = [
        'value' => $token->getValue(),
        'created' => $token->getCreated()->format('Y-m-d H:i:s'),
        'expires' => $token->getExpires()->format('Y-m-d H:i:s'),
    ];
}

// Verifica si hay una FIEL activa en la sesión y devuelve el RFC.
function ensureFiel(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $activeRfc = null;
    // Busca el RFC que tenga credenciales de FIEL guardadas
    foreach (array_keys($_SESSION['sat_data'] ?? []) as $rfc) {
        if (isset($_SESSION['sat_data'][$rfc]['fiel_credentials'])) {
            $activeRfc = $rfc;
            break;
        }
    }

    if (!$activeRfc) {
        respond(['success' => false, 'message' => 'Sesión no autenticada (FIEL).'], 401);
    }

    $service = getServiceInstance($activeRfc);
    if (!$service) {
        respond(['success' => false, 'message' => "FIEL inválida o expirada para el RFC $activeRfc"], 401);
    }
    return $activeRfc;
}


if (isset($argv) && count($argv) >= 3 && $argv[1] === '--background') {
    // EJECUCIÓN EN SEGUNDO PLANO 
    $idLocal = (int)$argv[2];
    $requestId = $argv[3] ?? '';
    $rfc = $argv[4] ?? ''; 

    if (empty($rfc)) {
        exit("Error: RFC necesario para la autenticación no proporcionado\n");
    }

    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $service = getServiceInstance($rfc);

    if (!$service) {
        $db->prepare('UPDATE cf_solicitudes SET estado=?, mensaje_error=?, ultima_verificacion=NOW() WHERE id_solicitud=?')
         ->execute(['error', 'No se pudo cargar la FIEL/Token para la verificación en segundo plano.', $idLocal]);
        exit("Error: No se pudo cargar la FIEL para el RFC $rfc\n");
    }

    try {
        // Ejecutar la verificación
        $verify = $service->verify($requestId);
        
        saveTokenToSession($rfc, $service->getToken());

    } catch (WebClientException $e) {
        $errorMessage = "Error de comunicación con SAT: " . $e->getMessage();
        $db->prepare('UPDATE cf_solicitudes SET estado=?, mensaje_error=?, ultima_verificacion=NOW() WHERE id_solicitud=?')
             ->execute(['error', $errorMessage, $idLocal]);
        exit("Error al verificar (WebClient): " . $e->getMessage());
    } catch (Throwable $e) {
        $db->prepare('UPDATE cf_solicitudes SET estado=?, mensaje_error=?, ultima_verificacion=NOW() WHERE id_solicitud=?')
             ->execute(['error', $e->getMessage(), $idLocal]);
        exit("Error al verificar: " . $e->getMessage());
    }

    // Procesar el resultado de la verificación
    $statusRequest = $verify->getStatusRequest();
    $estadoSAT = 'pendiente';

    if ($statusRequest->isFinished()) {
        $estadoSAT = 'terminada';
    } elseif ($statusRequest->isRejected()) {
        $estadoSAT = 'rechazada';
    } elseif ($statusRequest->isFailure()) {
        $estadoSAT = 'error';
    } elseif ($statusRequest->isExpired()) {
        $estadoSAT = 'vencida';
    } elseif ($statusRequest->isInProgress() || $statusRequest->isAccepted()) {
        $estadoSAT = 'aceptada';
    }
    
    // Actualizar la lista de paquetes si la solicitud está 'terminada'
    $paquetesList = json_decode($row['paquetes_json'] ?? '[]', true);
    $packageIds = $verify->getPackagesIds();
    
    if ($estadoSAT === 'terminada' && count($paquetesList) === 0) {
        foreach ($packageIds as $pid) {
            $paquetesList[] = [
                'package_id' => $pid,
                'estado' => 'pendiente',
                'zip_path' => null,
                'fecha_descarga' => null,
                'num_cfdis' => 0,
                'mensaje_error' => null,
                'procesado' => 0
            ];
        }
    }

    //Actualizar la base de datos con el nuevo estado y paquetes
    $upd = $db->prepare('UPDATE cf_solicitudes
         SET estado=?, total_paquetes=?, paquetes_json=?, ultima_verificacion=NOW(),
             fecha_terminada=CASE WHEN estado NOT IN ("terminada", "error", "rechazada", "vencida") AND ? IN ("terminada", "error", "rechazada", "vencida") THEN NOW() ELSE fecha_terminada END
         WHERE id_solicitud=?');
    
    $upd->execute([
        $estadoSAT, 
        count($packageIds),
        json_encode($paquetesList), 
        $estadoSAT, 
        $idLocal
    ]);

    exit("Verificación completada ($estadoSAT) para solicitud $idLocal\n");
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Sólo POST'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['success' => false, 'message' => 'JSON inválido'], 400);
}

$idLocal = (int)($input['id_solicitud'] ?? 0);
$requestId = trim($input['requestId'] ?? '');

if ($idLocal <= 0 && $requestId === '') {
    respond(['success' => false, 'message' => 'Debe enviar id_solicitud o requestId'], 400);
}

$rfcVerificacion = ensureFiel(); // Obtiene el RFC del usuario activo

if ($requestId === '') {
    $db = (new Database())->getConnection();
    $sel = $db->prepare('SELECT solicitud_id_sat FROM cf_solicitudes WHERE id_solicitud=?');
    $sel->execute([$idLocal]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        respond(['success' => false, 'message' => "No se encontró la solicitud local $idLocal"], 404);
    }
    $requestId = $row['solicitud_id_sat'];
}

respond([
    'success' => true,
    'message' => 'Verificación en proceso',
    'id_solicitud' => $idLocal,
    'requestId' => $requestId
]);

// Ejecutar en segundo plano la verificación
$phpPath = PHP_BINARY; 
$scriptPath = __FILE__;

$cmd = sprintf(
    '%s %s --background %d %s %s > /dev/null 2>&1 &',
    escapeshellarg($phpPath),
    escapeshellarg($scriptPath),
    $idLocal,
    escapeshellarg($requestId),
    escapeshellarg($rfcVerificacion) // FIX: Pasar el RFC aquí
);
exec($cmd);
exit;