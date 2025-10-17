<?php
// core/descargar-paquete-sat.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;
use PhpCfdi\SatWsDescargaMasiva\Shared\Token;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use GuzzleHttp\Client;

function logActivity($message)
{
    $logFile = __DIR__ . '/logs/descargar-paquete-sat.log';
    $timestamp = date('Y-m-d H:i:s');
    // Asegurar que la carpeta logs exista
    @mkdir(dirname($logFile), 0775, true); 
    file_put_contents($logFile, "[{$timestamp}] " . $message . "\n", FILE_APPEND);
}

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

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
        // Crear Fiel
        $fiel = Fiel::create($fielData['cer_content'], $fielData['key_content'], $fielData['passphrase']);
        if (!$fiel->isValid()) {
            return null;
        }
    } catch (\Throwable $e) {
        error_log("Error al crear la FIEL para $rfc: " . $e->getMessage());
        return null;
    }

    // Crea Token a partir de la sesión 
    if ($tokenData) {
        $token = new Token(
            DateTime::create($tokenData['created']),
            DateTime::create($tokenData['expires']),
            $tokenData['value']
        );
    }
    
    $webClient = new GuzzleWebClient(new Client(['timeout' => 120, 'verify' => false]));
    $requestBuilder = new FielRequestBuilder($fiel);
    return new Service($requestBuilder, $webClient, $token);
}

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

header('Content-Type: application/json; charset=utf-8');
logActivity("--- Inicio del proceso de descarga ---");

$input = json_decode(file_get_contents('php://input'), true);
$idLocal = (int)($input['id_solicitud'] ?? 0);

if ($idLocal <= 0) {
    logActivity("ERROR: Se recibió un ID de solicitud inválido ({$idLocal}).");
    respond(['success' => false, 'message' => 'ID de solicitud inválido.'], 400);
}

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
} catch (Throwable $e) {
    logActivity("ERROR: No se pudo conectar a la base de datos: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Error de conexión a BD.'], 500);
}

$stmt = $db->prepare('SELECT solicitud_id_sat, paquetes_json, rfc_emisor, rfc_receptor FROM cf_solicitudes WHERE id_solicitud = ? AND estado = ?');
$stmt->execute([$idLocal, 'terminada']); 
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    logActivity("ERROR: Solicitud {$idLocal} no encontrada o no está en estado 'terminada'.");
    respond(['success' => false, 'message' => 'Solicitud no encontrada o no lista para descargar.'], 404);
}

$paquetes = json_decode($row['paquetes_json'] ?? '[]', true);
if (!is_array($paquetes) || empty($paquetes)) {
    logActivity("INFO: No hay paquetes registrados en el JSON para la solicitud {$idLocal}.");
    respond(['success' => false, 'message' => 'No hay paquetes registrados para esta solicitud.'], 400);
}

$rfc = $row['rfc_emisor'] ?: $row['rfc_receptor'];
$service = getServiceInstance($rfc);


if (!$service) {
    logActivity("ERROR: No se pudo obtener Service Instance (FIEL/Token) para {$rfc}.");
    respond(['success' => false, 'message' => "No se pudo autenticar para el RFC {$rfc}. Vuelva a autenticar."], 401);
}

$baseTmp = __DIR__ . '/../uploads/tmp';
// CORRECCIÓN: Se agrega una comprobación para evitar el warning 'File exists' si ya existe.
if (!is_dir($baseTmp)) {
    mkdir($baseTmp, 0775, true);
}


$nuevosPaquetes = [];
$descargados = 0;
$actualizados = false;

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
        $actualizados = true;
        continue;
    }

    logActivity("Intentando descargar paquete {$pid} para solicitud {$idLocal}...");

    try {
        $downloadResult = $service->download($pid);
        $status = $downloadResult->getStatus();

        saveTokenToSession($rfc, $service->getToken());

        if (!$status->isAccepted()) {
            $p['estado'] = 'error';
            $p['mensaje_error'] = "SAT: [{$status->getCode()}] {$status->getMessage()}";
            logActivity("❌ ERROR descarga {$pid}: {$p['mensaje_error']}");
            $nuevosPaquetes[] = $p;
            $actualizados = true;
            continue;
        }

        $zipContents = $downloadResult->getPackageContent();
        
        // Validar que el contenido no esté vacío
        if (empty($zipContents)) {
            $p['estado'] = 'error';
            $p['mensaje_error'] = 'El SAT devolvió un contenido vacío para el paquete.';
            $nuevosPaquetes[] = $p;
            $actualizados = true;
            continue;
        }

        // Guardar ZIP final
        $pathDir = "{$baseTmp}/{$rfc}/{$idLocal}";
        // CORRECCIÓN: Se agrega una comprobación para evitar el warning 'File exists'
        if (!is_dir($pathDir)) {
             @mkdir($pathDir, 0775, true);
        }
        $zipFilePath = "{$pathDir}/{$pid}.zip";
        
    // Usar ruta relativa para guardar en BD (sin '/../' al inicio)
    $relativeZipPath = 'uploads/tmp/' . $rfc . '/' . $idLocal . '/' . $pid . '.zip';
        file_put_contents($zipFilePath, $zipContents);

        // Validar ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            $p['estado'] = 'error';
            $p['mensaje_error'] = "El archivo descargado no es un ZIP válido.";
            logActivity(" El contenido no es un ZIP válido. Ver archivo en: {$zipFilePath}");
            $nuevosPaquetes[] = $p;
            $actualizados = true;
            continue;
        }
        $numFiles = $zip->numFiles;
        $zip->close();

        $p['estado'] = 'descargado';
        $p['zip_path'] = $relativeZipPath;
        $p['fecha_descarga'] = date('Y-m-d H:i:s');
        $p['num_cfdis'] = $numFiles;
        $nuevosPaquetes[] = $p;
        $descargados++;
        $actualizados = true;

        logActivity("Paquete {$pid} descargado correctamente ({$numFiles} archivos) en {$relativeZipPath}.");

    } catch (WebClientException $e) {
        $p['estado'] = 'error';
        $p['mensaje_error'] = 'Error de comunicación (WebClient): ' . $e->getMessage();
        logActivity( "ERROR WebClient {$pid}: " . $e->getMessage());
        $nuevosPaquetes[] = $p;
        $actualizados = true;
    } catch (Throwable $e) {
        $p['estado'] = 'error';
        $p['mensaje_error'] = 'Error inesperado: ' . $e->getMessage();
        logActivity(" ERROR inesperado {$pid}: " . $e->getMessage());
        $nuevosPaquetes[] = $p;
        $actualizados = true;
    }
}

// actualizar la bd si hubo cambios
if ($actualizados) {
    $upd = $db->prepare('UPDATE cf_solicitudes SET paquetes_json = ?, ultima_verificacion = NOW() WHERE id_solicitud = ?');
    $upd->execute([json_encode($nuevosPaquetes, JSON_UNESCAPED_UNICODE), $idLocal]);
}

logActivity("--- Fin del proceso. Descargados: {$descargados} ---");

respond([
    'success' => true,
    'message' => "Descarga completada. Paquetes descargados: {$descargados}.",
    'descargados' => $descargados,
    'paquetes_actualizados' => $nuevosPaquetes
]);