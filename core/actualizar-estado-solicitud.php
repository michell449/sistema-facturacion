<?php
// core/actualizar-estado-solicitud.php

header('Content-Type: application/json; charset=utf-8');

// --- Dependencias ---
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

require_once __DIR__ . '/../config.php'; // Aquí supongo que config.php contiene session_start()
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use GuzzleHttp\Client;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

// --- Funciones de Ayuda ---

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function createService(Fiel $fiel): Service
{
    $client = new Client(['timeout' => 90]);
    $webClient = new GuzzleWebClient($client);
    return new Service(new FielRequestBuilder($fiel), $webClient);
}

function getFielFromSource(string $rfc): ?Fiel
{
    if (
        !isset($_SESSION['fiel_data']) ||
        !isset($_SESSION['fiel_data'][$rfc]) ||
        empty($_SESSION['fiel_data'][$rfc]['cer_content']) ||
        empty($_SESSION['fiel_data'][$rfc]['key_content']) ||
        empty($_SESSION['fiel_data'][$rfc]['passphrase'])
    ) {
        return null;
    }
    try {
        $fielData = $_SESSION['fiel_data'][$rfc];
        $fiel = Fiel::create(
            $fielData['cer_content'],
            $fielData['key_content'],
            $fielData['passphrase']
        );
        return $fiel->isValid() ? $fiel : null;
    } catch (Throwable $e) {
        error_log("Error al crear la FIEL desde la sesión para $rfc: " . $e->getMessage());
        return null;
    }
}

// --- Lógica Principal ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Sólo se permite POST'], 405);
}

try {
    $db = (new Database())->getConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    $idSolicitud = isset($input['id_solicitud']) ? (int)$input['id_solicitud'] : 0;

    $solicitudes = [];

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
            WHERE estado NOT IN ('terminada', 'error', 'rechazada', 'vencida', 'cancelada')
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

        $fiel = getFielFromSource($rfc);
        if (!$fiel) {
            $errores[] = "Error en Solicitud #$idLocal: No se pudo cargar la FIEL para el RFC $rfc. Por favor, autentíquese de nuevo.";
            continue;
        }

        $service = createService($fiel);

        try {
            $verify = $service->verify($requestId);
            $statusRequest = $verify->getStatusRequest();
            $estadoSAT = 'desconocido';

            if ($statusRequest->isFinished()) {
                $estadoSAT = 'terminada';
            } elseif ($statusRequest->isInProgress() || $statusRequest->isAccepted()) {
                $estadoSAT = 'aceptada';
            } elseif ($statusRequest->isRejected()) {
                $estadoSAT = 'rechazada';
            } elseif ($statusRequest->isFailure()) {
                $estadoSAT = 'error';
            } elseif ($statusRequest->isExpired()) {
                $estadoSAT = 'vencida';
            }

            $paquetesList = [];
            if ($estadoSAT === 'terminada') {
                $packageIds = $verify->getPackagesIds();
                foreach ($packageIds as $pid) {
                    $paquetesList[$pid] = [ // Usar el ID del paquete como clave para evitar duplicados
                        'package_id' => $pid,
                        'estado' => 'pendiente',
                        'zip_path' => null,
                        'fecha_descarga' => null,
                        'mensaje_error' => null,
                        'procesado' => 0
                    ];
                }

                $baseTmp = __DIR__ . '/../uploads/tmp';
                @mkdir($baseTmp, 0775, true);

                $rfcSelStmt = $db->prepare('SELECT rfc_emisor, rfc_receptor FROM cf_solicitudes WHERE id_solicitud = ?');
                $rfcSelStmt->execute([$idLocal]);
                $rr = $rfcSelStmt->fetch(PDO::FETCH_ASSOC);
                $rfcForPath = $rr['rfc_emisor'] ?: $rr['rfc_receptor'];

                foreach ($paquetesList as $pid => &$p) {
                    try {
                        // --- INICIO DE LA CORRECCIÓN ---

                        // 1. La función download() devuelve un objeto DownloadResult.
                        $downloadResult = $service->download($pid);

                        // 2. Obtenemos el contenido del paquete, que está en base64.
                        $packageContent = $downloadResult->getPackageContent();

                        // 3. Decodificamos el contenido de base64 a binario.
                        $zipData = base64_decode($packageContent);

                        if ($zipData === false) {
                            throw new \Exception("El contenido del paquete {$pid} no es un base64 válido.");
                        }

                        $pathDir = $baseTmp . '/' . $rfcForPath . '/' . $idLocal;
                        @mkdir($pathDir, 0775, true);

                        $filename = $pathDir . '/' . $pid . '.zip';

                        // 4. Guardamos el contenido ya decodificado.
                        $bytes = file_put_contents($filename, $zipData);

                        // --- FIN DE LA CORRECCIÓN ---

                        if ($bytes === false) {
                            throw new \Exception("No se pudo escribir el archivo ZIP: $filename");
                        }

                        $relPath = "uploads/tmp/{$rfcForPath}/{$idLocal}/{$pid}.zip";

                        $p['estado'] = 'descargado';
                        $p['zip_path'] = $relPath;
                        $p['fecha_descarga'] = date('Y-m-d H:i:s');
                    } catch (Throwable $e) {
                        $p['estado'] = 'error';
                        $p['mensaje_error'] = $e->getMessage();
                    }
                }
                unset($p); // romper referencia
            }

            // Conservar información de paquetes ya descargados si se vuelve a verificar
            $paquetesPrevios = json_decode($solicitud['paquetes_json'] ?? '[]', true);
            foreach ($paquetesPrevios as $previo) {
                if (isset($previo['package_id']) && isset($paquetesList[$previo['package_id']]) && $paquetesList[$previo['package_id']]['estado'] === 'pendiente') {
                    $paquetesList[$previo['package_id']] = $previo;
                }
            }

            $upd = $db->prepare(
                'UPDATE cf_solicitudes 
             SET estado = ?, ultima_verificacion = ?, paquetes_json = ?, total_paquetes = ?, fecha_terminada = CASE WHEN ? = "terminada" THEN ? ELSE fecha_terminada END
                WHERE id_solicitud = ?'
            );

            $paquetesJson = json_encode(array_values($paquetesList)); // Re-indexar el array
            $totalPaqueteCount = count($paquetesList);

            $upd->execute([
                $estadoSAT,
                $ahora,
                $paquetesJson,
                $totalPaqueteCount,
                $estadoSAT,
                $ahora,
                $idLocal
            ]);

            $nuevosEstados[$idLocal] = $estadoSAT;
            $verificadas++;
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
