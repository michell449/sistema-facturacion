<?php
// core/verificar-descarga.php

// === CONFIGURACIÓN DE ERRORES ===
// Asegura que no se imprima HTML que rompa el JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); 
ini_set('log_errors', 1); 
ini_set('error_log', __DIR__ . '/../logs/php_error.log'); 

header('Content-Type: application/json; charset=utf-8');

// Carga de autoload.php
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
require_once __DIR__ . '/class/db.php'; // Asumiendo que Database existe

// === USES DE NAMESPACE ===
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;
use PhpCfdi\SatWsDescargaMasiva\Shared\Token;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use GuzzleHttp\Client;

// === FUNCIONES AUXILIARES ===

// Función de log simple para errores
function logVerify(string $message): void
{
    $logFile = __DIR__ . '/../logs/verificar-descarga.log';
    $timestamp = date('Y-m-d H:i:s');
    @mkdir(dirname($logFile), 0775, true); 
    file_put_contents($logFile, "[{$timestamp}] " . $message . "\n", FILE_APPEND);
}

function respond(array $data, int $code = 200): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getServiceInstance(string $rfc): ?Service
{
    if (!isset($_SESSION['sat_data'][$rfc]['fiel_credentials'])) {
        return null;
    }
    $fielData = $_SESSION['sat_data'][$rfc]['fiel_credentials'];
    $tokenData = $_SESSION['sat_data'][$rfc]['token_data'] ?? null;
    $token = null;

    try {
        $fiel = Fiel::create($fielData['cer_content'], $fielData['key_content'], $fielData['passphrase']);
        if (!$fiel->isValid()) {
            return null;
        }
    } catch (\Throwable $e) {
        return null;
    }

    if ($tokenData) {
        try {
            $token = new Token(
                DateTime::create($tokenData['created']),
                DateTime::create($tokenData['expires']),
                $tokenData['value']
            );
        } catch (\Throwable $e) {
            $token = null;
        }
    }
    
    $webClient = new GuzzleWebClient(new Client(['timeout' => 90]));
    $requestBuilder = new FielRequestBuilder($fiel);
    return new Service($requestBuilder, $webClient, $token);
}

function saveTokenToSession(string $rfc, Token $token): void
{
    $_SESSION['sat_data'][$rfc]['token_data'] = [
        'value' => $token->getValue(),
        'created' => $token->getCreated()->format('Y-m-d H:i:s'),
        'expires' => $token->getExpires()->format('Y-m-d H:i:s'),
    ];
}

function ensureFiel(): string
{
    $activeRfc = null;
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Sólo POST'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['success' => false, 'message' => 'JSON inválido'], 400);
}

$idLocal = (int)($input['id_solicitud'] ?? 0);
$requestId = trim($input['requestId'] ?? '');

if ($idLocal <= 0) {
    respond(['success' => false, 'message' => 'Debe enviar id_solicitud (local)'], 400);
}

try {
    // 1. Obtener RFC y Service
    $rfc = ensureFiel(); 
    $service = getServiceInstance($rfc);

    // 2. Obtener datos de la solicitud de la BD
    $db = (new Database())->getConnection();
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    
    $sel = $db->prepare('SELECT solicitud_id_sat, paquetes_json, estado FROM cf_solicitudes WHERE id_solicitud=?');
    $sel->execute([$idLocal]);
    $row = $sel->fetch(\PDO::FETCH_ASSOC);
    
    if (!$row) {
        respond(['success' => false, 'message' => "No se encontró la solicitud local $idLocal"], 404);
    }

    $requestId = $requestId ?: $row['solicitud_id_sat'];
    $paquetesList = json_decode($row['paquetes_json'] ?? '[]', true);
    $estadoOriginal = $row['estado'];

    // 3. Realizar la verificación
    $verifyResult = $service->verify($requestId);
    
    // 4. Guardar el token actualizado
    saveTokenToSession($rfc, $service->getToken());

    // 5. Determinar el nuevo estado
    $statusRequest = $verifyResult->getStatusRequest();
    $estadoSAT = $estadoOriginal; 

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
    
    // 6. Actualizar la lista de paquetes si está terminada y vacía
    $packageIds = $verifyResult->getPackagesIds();
    
    if ($estadoSAT === 'terminada' && count($paquetesList) === 0 && count($packageIds) > 0) {
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

    // 7. Actualizar la base de datos (clave: ultima_verificacion=NOW() y estado)
    $upd = $db->prepare('UPDATE cf_solicitudes
             SET estado=?, total_paquetes=?, paquetes_json=?, ultima_verificacion=NOW(),
                 fecha_terminada=CASE WHEN estado NOT IN ("terminada", "error", "rechazada", "vencida") AND ? IN ("terminada", "error", "rechazada", "vencida") THEN NOW() ELSE fecha_terminada END
             WHERE id_solicitud=?');
    
    $upd->execute([
        $estadoSAT, 
        count($packageIds), 
        json_encode($paquetesList, JSON_UNESCAPED_UNICODE), 
        $estadoSAT, 
        $idLocal
    ]);
    
    // 8. Responder con el nuevo estado
    $messageStatus = $statusRequest->getMessage() ?: 'Estado actualizado correctamente.';
    if ($estadoSAT !== $estadoOriginal) {
        $messageStatus = "Estado actualizado a: " . strtoupper($estadoSAT);
    }

    respond([
        'success' => true,
        'message' => $messageStatus,
        'id_solicitud' => $idLocal,
        'requestId' => $requestId,
        'nuevo_estado' => $estadoSAT,
        'estado_sat_code' => $statusRequest->getCode()
    ]);

} catch (WebClientException $e) {
    logVerify("Error WebClient para $idLocal: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Error de comunicación con el SAT: ' . $e->getMessage()], 503);
} catch (\Throwable $e) {
    logVerify("Error inesperado en verificación $idLocal: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Error interno al procesar la verificación: ' . $e->getMessage()], 500);
}