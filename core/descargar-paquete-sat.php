<?php
// core/descargar-paquete-sat.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use GuzzleHttp\Client;

function logActivity($message)
{
    $logFile = __DIR__ . '/logs/descargar-paquete-sat.log';
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

try {
    $db = (new Database())->getConnection(); // PDO
} catch (Throwable $e) {
    logActivity("ERROR: No se pudo conectar a la base de datos: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Error de conexión a BD.'], 500);
}

$stmt = $db->prepare('SELECT solicitud_id_sat, paquetes_json, rfc_emisor, rfc_receptor FROM cf_solicitudes WHERE id_solicitud = ?');
$stmt->execute([$idLocal]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    logActivity("ERROR: No se encontró la solicitud con ID {$idLocal}.");
    respond(['success' => false, 'message' => 'Solicitud no encontrada.'], 404);
}

$paquetes = json_decode($row['paquetes_json'] ?? '[]', true);
if (empty($paquetes)) {
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
        $p['mensaje_error'] = 'El ID del paquete está vacío.';
        $nuevosPaquetes[] = $p;
        continue;
    }

    logActivity("Intentando descargar paquete {$pid}...");

    try {
        $downloadResult = $service->download($pid);
        $zipContents = $downloadResult->getPackageContent();

        // Guardar respuesta cruda del SAT
        $rawFile = $baseTmp . "/RAW_{$pid}.bin";
        file_put_contents($rawFile, $zipContents);
        logActivity("Guardada respuesta cruda del SAT en: {$rawFile} (" . strlen($zipContents) . " bytes)");

        // Detectar tipo MIME real
        $tmpMimeFile = tempnam(sys_get_temp_dir(), 'mime_');
        file_put_contents($tmpMimeFile, $zipContents);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpMimeFile);
        finfo_close($finfo);
        unlink($tmpMimeFile);
        logActivity("Tipo MIME detectado del paquete {$pid}: {$mime}");

        // Registrar si parece XML o HTML
        if (stripos($zipContents, '<?xml') === 0 || stripos($zipContents, '<html') !== false) {
            logActivity("⚠️ El SAT devolvió texto (XML/HTML), no un ZIP válido para {$pid}. Guardado para análisis.");
            $p['estado'] = 'error';
            $p['mensaje_error'] = 'El SAT devolvió texto (XML/HTML), no un ZIP válido.';
            $nuevosPaquetes[] = $p;
            continue;
        }

        // Intentar decodificar base64
        $decoded = @base64_decode($zipContents, true);
        if ($decoded !== false && strlen($decoded) > 100) {
            logActivity("El contenido del SAT parece estar codificado en base64, decodificando...");
            $zipContents = $decoded;
        }

        // Guardar ZIP final
        $pathDir = "{$baseTmp}/{$rfc}/{$idLocal}";
        @mkdir($pathDir, 0775, true);
        $zipFilePath = "{$pathDir}/{$pid}.zip";
        file_put_contents($zipFilePath, $zipContents);

        // Validar ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new Exception("El archivo descargado no es un ZIP válido. Ver RAW_{$pid}.bin");
        }
        $numFiles = $zip->numFiles;
        $zip->close();

        if ($numFiles === 0) {
            throw new Exception("El ZIP está vacío.");
        }

        $p['estado'] = 'descargado';
        $p['zip_path'] = str_replace(realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR, '', realpath($zipFilePath));
        $nuevosPaquetes[] = $p;
        $descargados++;

        logActivity("✅ Paquete {$pid} descargado correctamente ({$numFiles} archivos).");
    } catch (Throwable $e) {
        $p['estado'] = 'error';
        $p['mensaje_error'] = $e->getMessage();
        logActivity("❌ ERROR en paquete {$pid}: " . $e->getMessage());
        $nuevosPaquetes[] = $p;
    }
}

$upd = $db->prepare('UPDATE cf_solicitudes SET paquetes_json = ?, ultima_verificacion = NOW() WHERE id_solicitud = ?');
$upd->execute([json_encode($nuevosPaquetes, JSON_UNESCAPED_UNICODE), $idLocal]);

logActivity("--- Fin del proceso. Descargados: {$descargados} ---");

respond([
    'success' => true,
    'message' => "Descarga completada. Paquetes descargados: {$descargados}.",
    'descargados' => $descargados,
    'paquetes' => $nuevosPaquetes
]);
