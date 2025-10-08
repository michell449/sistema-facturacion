<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use GuzzleHttp\Client;

header('Content-Type: application/json; charset=utf-8');

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureFiel(): Fiel
{
    session_start();
    if (!isset($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase'])) {
        respond(['success' => false, 'message' => 'Sesión no autenticada con FIEL'], 401);
    }
    try {
        $fiel = Fiel::create($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase']);
        if (!$fiel->isValid()) {
            respond(['success' => false, 'message' => 'FIEL inválida o expirada'], 401);
        }
        return $fiel;
    } catch (Throwable $e) {
        respond(['success' => false, 'message' => 'Error al cargar FIEL: ' . $e->getMessage()], 500);
    }
}

function createService(Fiel $fiel): Service
{
    $client = new Client(['timeout' => 90]);
    $webClient = new GuzzleWebClient($client);
    return new Service(new FielRequestBuilder($fiel), $webClient);
}

$idSolicitud = (int)($_POST['id_solicitud'] ?? 0);
if ($idSolicitud <= 0) {
    respond(['success' => false, 'message' => 'ID de solicitud inválido'], 400);
}

// Crear conexión
$db = (new Database())->getConnection();

$sel = $db->prepare('SELECT solicitud_id_sat, paquetes_json,  FROM cf_solicitudes WHERE id_solicitud = ?');
$sel->execute([$idSolicitud]);
$row = $sel->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    respond(['success' => false, 'message' => 'Solicitud no encontrada'], 404);
}

$paquetes = json_decode($row['paquetes_json'] ?? '[]', true);
if (!is_array($paquetes) || empty($paquetes)) {
    respond(['success' => false, 'message' => 'No hay paquetes para descargar'], 400);
}

$fiel = ensureFiel();
$service = createService($fiel);
$rfc = $row['rfc'] ?? 'desconocido';

// Ruta de descarga
$basePath = __DIR__ . '/../descargas';
$downloadPath = $basePath . '/' . $rfc . '/' . $idSolicitud;
@mkdir($downloadPath, 0775, true);

$nuevosPaquetes = [];
$descargados = 0;

foreach ($paquetes as $p) {
    if (($p['estado'] ?? '') !== 'pendiente') {
        $nuevosPaquetes[] = $p;
        continue;
    }

    $packageId = $p['package_id'] ?? '';
    if (!$packageId) continue;

    try {
        $zipContents = $service->download($packageId);
        $zipFilename = $downloadPath . '/' . $packageId . '.zip';

        file_put_contents($zipFilename, $zipContents);

        $p['estado'] = 'descargado';
        $p['zip_path'] = $zipFilename;
        $p['fecha_descarga'] = date('Y-m-d H:i:s');
        $descargados++;
    } catch (Throwable $e) {
        $p['estado'] = 'error';
        $p['mensaje_error'] = $e->getMessage();
    }

    $nuevosPaquetes[] = $p;
}

$upd = $db->prepare('UPDATE cf_solicitudes SET paquetes_json = ?, ultima_verificacion = NOW() WHERE id_solicitud = ?');
$upd->execute([json_encode($nuevosPaquetes), $idSolicitud]);

respond([
    'success' => true,
    'message' => "Se descargaron $descargados paquete(s)",
    'descargados' => $descargados,
    'paquetes' => $nuevosPaquetes
]);
