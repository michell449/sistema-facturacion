<?php
$autoloadPrimary = __DIR__ . '/../vendor/autoload.php';
$autoloadFallback = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPrimary)) { require_once $autoloadPrimary; }
elseif (file_exists($autoloadFallback)) { require_once $autoloadFallback; }
else { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Sin autoload composer']); exit; }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use GuzzleHttp\Client;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

function respond($data,$code=200){ http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

function ensureFiel(): Fiel {
    if (!isset($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase'])) {
        respond(['success'=>false,'message'=>'SesiÃ³n no autenticada (FIEL)'],401);
    }
    try {
        $fiel = Fiel::create($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase']);
        if (!$fiel->isValid()) respond(['success'=>false,'message'=>'FIEL invÃ¡lida o expirada'],401);
        return $fiel;
    } catch (Throwable $e) {
        respond(['success'=>false,'message'=>'Error FIEL: '.$e->getMessage()],500);
    }
}

function createService(Fiel $fiel): Service {
    $client = new Client(['timeout' => 90]);
    $webClient = new GuzzleWebClient($client);
    return new Service(new FielRequestBuilder($fiel), $webClient);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success'=>false,'message'=>'SÃ³lo POST'],405);
}
$raw = file_get_contents('php://input');
$input = json_decode($raw,true);
if (!is_array($input)) respond(['success'=>false,'message'=>'JSON invÃ¡lido'],400);

$idLocal = isset($input['id_solicitud']) ? (int)$input['id_solicitud'] : 0;
$requestId = trim($input['requestId'] ?? '');
$minimal = (bool)($input['minimal'] ?? false);

$db = (new Database())->getConnection();

if ($idLocal <= 0 && $requestId === '') {
    respond(['success'=>false,'message'=>'Debe enviar id_solicitud o requestId'],400);
}

if ($idLocal > 0) {
    $sel = $db->prepare('SELECT id_solicitud, solicitud_id_sat, paquetes_json, estado FROM cf_solicitudes WHERE id_solicitud=?');
    $sel->execute([$idLocal]);
} else { // buscar por requestId SAT
    $sel = $db->prepare('SELECT id_solicitud, solicitud_id_sat, paquetes_json, estado FROM cf_solicitudes WHERE solicitud_id_sat=?');
    $sel->execute([$requestId]);
}
$row = $sel->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    respond(['success'=>false,'message'=>'Solicitud no encontrada'],404);
}
$idLocal = (int)$row['id_solicitud'];
$requestId = $row['solicitud_id_sat'];

$fiel = ensureFiel();
$service = createService($fiel);

try {
    $verify = $service->verify($requestId);
} catch (Throwable $e) {
    respond(['success'=>false,'message'=>'Error al invocar verificaciÃ³n: '.$e->getMessage()],500);
}

/** @var \PhpCfdi\SatWsDescargaMasiva\Verify\VerifyResult $verify */

$status = $verify->getStatus();
$packageIds = $verify->getPackagesIds();
$isFinished = $verify->isFinished();

// Determinar estado interno
$estado = 'pendiente';
if ($status->isAccepted()) {
    $estado = $isFinished ? 'terminada' : 'aceptada';
} else {
    $estado = 'rechazada';
}

// Integrar paquetes en JSON
$existing = [];
if (!empty($row['paquetes_json'])) {
    $decoded = json_decode($row['paquetes_json'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $p) { if(isset($p['package_id'])) $existing[$p['package_id']]=$p; }
    }
}
$nuevos = [];
foreach ($packageIds as $pid) {
    if (!isset($existing[$pid])) {
        $existing[$pid] = [
            'package_id'=>$pid,
            'estado'=>'pendiente',
            'zip_path'=>null,
            'fecha_descarga'=>null,
            'num_cfdis'=>0,
            'mensaje_error'=>null,
            'procesado'=>0
        ];
        $nuevos[] = $pid;
    }
}
$paquetesList = array_values($existing);
$totalPaquetes = count($paquetesList);
$descargados = 0; $procesados = 0;
foreach ($paquetesList as $p) {
    if (in_array(($p['estado']??''), ['descargado','procesado'])) $descargados++;
    if (($p['procesado']??0)==1) $procesados++;
}

$upd = $db->prepare('UPDATE cf_solicitudes SET estado=?, total_paquetes=?, paquetes_json=?, ultima_verificacion=NOW(), fecha_terminada=CASE WHEN ?="terminada" THEN NOW() ELSE fecha_terminada END WHERE id_solicitud=?');
$upd->execute([$estado, $totalPaquetes, json_encode($paquetesList), $estado, $idLocal]);

$response = [
    'success'=>true,
    'id_solicitud'=>$idLocal,
    'requestId'=>$requestId,
    'estado'=>$estado,
    'status_code'=>$status->getCode(),
    'status_message'=>$status->getMessage(),
    'is_finished'=>$isFinished,
    'total_paquetes'=>$totalPaquetes,
    'packageIds'=>$packageIds,
    'nuevos'=>$nuevos,
    'descargados'=>$descargados,
    'procesados'=>$procesados,
];
if (!$minimal) {
    $response['paquetes'] = $paquetesList;
}

respond($response,200);
