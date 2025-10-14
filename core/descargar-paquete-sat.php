<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use GuzzleHttp\Client;

// --- Logger simple ---
function logActivity($message)
{
    $logFile = __DIR__ . '/../logs/descargar_paquete_sat.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] " . $message . "\n", FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureFiel(): Fiel
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase'])) {
        logActivity("ERROR: Fiel no encontrada en la sesión.");
        respond(['success' => false, 'message' => 'Sesión no autenticada con FIEL.'], 401);
    }
    try {
        $fiel = Fiel::create($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase']);
        if (!$fiel->isValid()) {
            logActivity("ERROR: La FIEL en sesión ha expirado o es inválida.");
            respond(['success' => false, 'message' => 'La FIEL es inválida o ha expirado.'], 401);
        }
        return $fiel;
    } catch (Throwable $e) {
        logActivity("CRITICAL: No se pudo crear la FIEL desde la sesión. Error: " . $e->getMessage());
        respond(['success' => false, 'message' => 'Error al cargar la FIEL: ' . $e->getMessage()], 500);
    }
}

function createService(Fiel $fiel): Service
{
    $client = new Client(['timeout' => 90, 'verify' => false]);
    $webClient = new GuzzleWebClient($client);
    return new Service(new FielRequestBuilder($fiel), $webClient);
}

logActivity("--- Inicio del proceso de descarga ---");

$input = json_decode(file_get_contents('php://input'), true);
$idLocal = (int)($input['id_solicitud'] ?? 0);
if ($idLocal <= 0) {
    logActivity("ERROR: Se recibió un ID de solicitud inválido ({$idLocal}).");
    respond(['success' => false, 'message' => 'ID de solicitud inválido.'], 400);
}

logActivity("Procesando solicitud ID: {$idLocal}");

$db = (new Database())->getConnection();
$sel = $db->prepare('SELECT solicitud_id_sat, paquetes_json, rfc_emisor, rfc_receptor FROM cf_solicitudes WHERE id_solicitud = ?');
$sel->execute([$idLocal]);
$row = $sel->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    logActivity("ERROR: No se encontró la solicitud con ID {$idLocal} en la base de datos.");
    respond(['success' => false, 'message' => 'Solicitud no encontrada.'], 404);
}

$paquetes = json_decode($row['paquetes_json'] ?? '[]', true);
if (!is_array($paquetes) || empty($paquetes)) {
    logActivity("INFO: No hay paquetes para procesar en la solicitud {$idLocal}.");
    respond(['success' => false, 'message' => 'No hay paquetes pendientes en esta solicitud.'], 400);
}

$fiel = ensureFiel();
$service = createService($fiel);

$rfc = $row['rfc_emisor'] ?: $row['rfc_receptor'];
$baseTmp = __DIR__ . '/../uploads/tmp';
@mkdir($baseTmp, 0775, true);

$nuevosPaquetes = [];
$descargados = 0;

foreach ($paquetes as $p) {
    if (($p['estado'] ?? '') !== 'pendiente') {
        $nuevosPaquetes[] = $p;
        continue;
    }

    $pid = $p['package_id'] ?? '';
    if (!$pid) {
        $p['estado'] = 'error';
        $p['mensaje_error'] = 'El ID del paquete (package_id) está vacío.';
        $nuevosPaquetes[] = $p;
        logActivity("ERROR: Paquete sin ID en la solicitud {$idLocal}.");
        continue;
    }

    logActivity("Intentando descargar paquete {$pid} para la solicitud {$idLocal}.");

    try {
        $downloadResult = $service->download($pid);
        $zipContents = $downloadResult->getPackageContent();

        // Guardar siempre el contenido crudo para depuración
        $debugFile = $baseTmp . "/DEBUG_{$pid}.txt";
        file_put_contents($debugFile, $zipContents);

        // Crear un archivo temporal para la validación con ZipArchive
        $tempZipPath = tempnam(sys_get_temp_dir(), 'zip_validation');
        file_put_contents($tempZipPath, $zipContents);

        $zip = new ZipArchive();
        $res = $zip->open($tempZipPath);

        if ($res !== true) {
            unlink($tempZipPath); // Limpiar archivo temporal
            $errorMsg = "El paquete [{$pid}] no es un archivo ZIP válido (Error de ZipArchive: {$res}). La respuesta del SAT se guardó en {$debugFile}.";

            // Intentar leer el error del SAT
            $xmlError = @simplexml_load_string($zipContents);
            if ($xmlError && isset($xmlError->Codigo, $xmlError->Mensaje)) {
                $errorMsg .= " Mensaje del SAT: [{$xmlError->Codigo}] {$xmlError->Mensaje}";
            }

            throw new \Exception($errorMsg);
        }

        $zip->close();
        unlink($tempZipPath); // Limpiar archivo temporal

        // Si es válido, guardar el archivo ZIP definitivo
        $pathDir = $baseTmp . '/' . $rfc . '/' . $idLocal;
        @mkdir($pathDir, 0775, true);
        $filename = $pathDir . '/' . $pid . '.zip';

        if (file_put_contents($filename, $zipContents) === false) {
            throw new \Exception("Error al escribir el archivo ZIP en disco para el paquete [{$pid}].");
        }

        $relPath = "uploads/tmp/{$rfc}/{$idLocal}/{$pid}.zip";
        $p['estado'] = 'descargado';
        $p['zip_path'] = $relPath;
        $p['fecha_descarga'] = date('Y-m-d H:i:s');
        $descargados++;
        logActivity("ÉXITO: Paquete {$pid} descargado y guardado correctamente.");
    } catch (Throwable $e) {
        $p['estado'] = 'error';
        $p['mensaje_error'] = $e->getMessage();
        logActivity("ERROR al procesar paquete {$pid}: " . $e->getMessage());
    }

    $nuevosPaquetes[] = $p;
}

$upd = $db->prepare('UPDATE cf_solicitudes SET paquetes_json = ?, ultima_verificacion = NOW() WHERE id_solicitud = ?');
$upd->execute([json_encode($nuevosPaquetes), $idLocal]);

logActivity("--- Fin del proceso de descarga para la solicitud {$idLocal}. Descargados: {$descargados}. ---");

respond([
    'success' => true,
    'message' => "Proceso de descarga finalizado. Se intentaron descargar todos los paquetes.",
    'descargados' => $descargados,
    'paquetes' => $nuevosPaquetes
]);
