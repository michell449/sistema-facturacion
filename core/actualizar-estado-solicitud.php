<?php
// core/actualizar-estado-solicitud.php

header('Content-Type: application/json; charset=utf-8');

// Incluir el autoload de Composer
$autoloadPrimary = __DIR__ . '/../vendor/autoload.php';
$autoloadFallback = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPrimary)) {
    require_once $autoloadPrimary;
} elseif (file_exists($autoloadFallback)) {
    require_once $autoloadFallback;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falta autoload de Composer']);
    exit;
}

// Iniciar sesión para acceder al Token y FIEL
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

/**
 * Crea una instancia de Service lista para usarse, inyectando el Token de la sesión si existe.
 * Si el Token está expirado, Service intentará autenticarse automáticamente.
 */
function getServiceInstance(string $rfc): ?Service
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Usar la clave de sesión 'sat_data' del primer paso de autenticación
    if (!isset($_SESSION['sat_data'][$rfc]['fiel_credentials'])) {
        return null;
    }
    $fielData = $_SESSION['sat_data'][$rfc]['fiel_credentials'];
    $tokenData = $_SESSION['sat_data'][$rfc]['token_data'] ?? null;
    $token = null;

    try {
        // 1. Crear Fiel
        $fiel = Fiel::create($fielData['cer_content'], $fielData['key_content'], $fielData['passphrase']);
        if (!$fiel->isValid()) {
            return null;
        }
    } catch (\Throwable $e) {
        error_log("Error al crear la FIEL para $rfc: " . $e->getMessage());
        return null;
    }

    // 2. Crear Token a partir de la sesión si existe
    if ($tokenData) {
        $token = new Token(
            DateTime::create($tokenData['created']),
            DateTime::create($tokenData['expires']),
            $tokenData['value']
        );
    }
    
    // 3. Crear Service (con Token o sin él)
    $webClient = new GuzzleWebClient(new Client(['timeout' => 90]));
    $requestBuilder = new FielRequestBuilder($fiel);
    return new Service($requestBuilder, $webClient, $token);
}

/**
 * Guarda el Token de autenticación en la sesión
 */
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Sólo se permite POST'], 405);
}

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $input = json_decode(file_get_contents('php://input'), true);
    $idSolicitud = isset($input['id_solicitud']) ? (int)$input['id_solicitud'] : 0;

    $solicitudes = [];

    // Lógica para obtener las solicitudes a verificar
    if ($idSolicitud > 0) {
        $stmt = $db->prepare('SELECT * FROM cf_solicitudes WHERE id_solicitud = ?');
        $stmt->execute([$idSolicitud]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($solicitud) {
            $solicitudes[] = $solicitud;
        }
    } else {
        $stmt = $db->query("
             SELECT * FROM cf_solicitudes
             WHERE estado NOT IN ('terminada', 'error', 'rechazada', 'vencida')
             OR (estado = 'aceptada' AND ultima_verificacion < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
             ORDER BY ultima_verificacion ASC 
             LIMIT 100 
         ");
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    if (empty($solicitudes)) {
        respond(['success' => true, 'message' => 'No hay solicitudes que necesiten verificación.', 'verificadas' => 0]);
    }

    $verificadas = 0;
    $errores = [];
    $ahora = date('Y-m-d H:i:s');
    $nuevosEstados = [];

    foreach ($solicitudes as $solicitud) {
        $idLocal = $solicitud['id_solicitud'];
        $requestId = $solicitud['solicitud_id_sat'];
        $rfc = $solicitud['rfc_emisor'] ?: $solicitud['rfc_receptor'];

        if (empty($rfc)) {
            $errores[] = "Error en Solicitud #$idLocal: No tiene un RFC asociado.";
            continue;
        }

        // 1. Obtener Service (incluye Fiel y Token de sesión, o autentica si expiró)
        $service = getServiceInstance($rfc);
        if (!$service) {
            $errores[] = "Error en Solicitud #$idLocal: No se pudo cargar la FIEL/Token para el RFC $rfc.";
            $db->prepare('UPDATE cf_solicitudes SET estado = ?, ultima_verificacion = ?, mensaje_error = ? WHERE id_solicitud = ?')
                 ->execute(['error', $ahora, "No se pudo cargar la FIEL/Token para el RFC $rfc. Sesión expirada.", $idLocal]);
            continue;
        }
        
        try {
            // 2. Ejecutar la verificación
            $verify = $service->verify($requestId);

            // 3. Persistir el Token si se generó uno nuevo (clave para la siguiente solicitud en el ciclo)
            saveTokenToSession($rfc, $service->getToken());

            $statusRequest = $verify->getStatusRequest();
            $estadoSAT = 'desconocido';
            
            // Re-initialize $paquetesList with old data to preserve download status if they exist
            $paquetesList = json_decode($solicitud['paquetes_json'] ?? '[]', true);
            $paquetesList = array_column($paquetesList, null, 'package_id');


            // 4. Determinar el estado
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
            
            // 5. Si el estado es 'terminada' y la DB aún no lo sabe, se actualiza la lista de paquetes.
            if ($estadoSAT === 'terminada' && $solicitud['estado'] !== 'terminada') {
                $packageIds = $verify->getPackagesIds();
                $nuevosPaquetes = [];
                
                foreach ($packageIds as $pid) {
                    $pid = strval($pid);
                    // Mantener el estado de descarga si ya existía el paquete (para no re-descargar)
                    $nuevosPaquetes[$pid] = $paquetesList[$pid] ?? [ 
                        'package_id' => $pid,
                        'estado' => 'pendiente', 
                        'zip_path' => null,
                        'fecha_descarga' => null,
                        'num_cfdis' => 0,
                        'mensaje_error' => null,
                        'procesado' => 0
                    ];
                }
                $paquetesList = $nuevosPaquetes;
            }
            
            // 6. Actualizar la base de datos
            $upd = $db->prepare(
                'UPDATE cf_solicitudes 
                SET estado = ?, ultima_verificacion = ?, paquetes_json = ?, total_paquetes = ?, 
                    fecha_terminada = CASE WHEN estado NOT IN ("terminada", "error", "rechazada", "vencida") AND ? IN ("terminada", "error", "rechazada", "vencida") THEN NOW() ELSE fecha_terminada END
                WHERE id_solicitud = ?'
            );

            // Convertir la lista de paquetes de nuevo a un array indexado numéricamente para JSON
            $paquetesJson = json_encode(array_values($paquetesList)); 
            $totalPaqueteCount = count($paquetesList);
            
            $upd->execute([
                $estadoSAT,
                $ahora,
                $paquetesJson,
                $totalPaqueteCount,
                $estadoSAT,
                $idLocal
            ]);

            $nuevosEstados[$idLocal] = $estadoSAT;
            $verificadas++;

        } catch (WebClientException $e) {
            $errorMessage = "Error de WebClient: " . $e->getMessage() . " Cuerpo: " . $e->getResponse()->getBody();
            $errores[] = $errorMessage;

            $db->prepare('UPDATE cf_solicitudes SET estado = ?, ultima_verificacion = ?, mensaje_error = ? WHERE id_solicitud = ?')
                ->execute(['error', $ahora, $e->getMessage(), $idLocal]);
        } catch (Throwable $e) {
            $errorMessage = "Error al verificar la solicitud #$idLocal: " . $e->getMessage();
            $errores[] = $errorMessage;

            $db->prepare('UPDATE cf_solicitudes SET estado = ?, ultima_verificacion = ?, mensaje_error = ? WHERE id_solicitud = ?')
                ->execute(['error', $ahora, $e->getMessage(), $idLocal]);
        }
    }

    $message = "Se procesaron $verificadas solicitud(es).";
    if (!empty($errores)) {
        $message .= " Se encontraron " . count($errores) . " errores.";
    }

    respond([
        'success' => true,
        'message' => $message,
        'verificadas' => $verificadas,
        'nuevos_estados' => $nuevosEstados,
        'errores' => $errores
    ]);
} catch (Throwable $e) {
    error_log("Error fatal en actualizar-estado-solicitud.php: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
}