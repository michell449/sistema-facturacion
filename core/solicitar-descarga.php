<?php
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
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use GuzzleHttp\Client;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcMatch;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcOnBehalf;
use PhpCfdi\SatWsDescargaMasiva\Shared\Uuid;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceType;

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validateRfc(string $rfc): bool
{
    // RFC persona moral o física (simplificado, incluye & y Ñ). No valida dígito verificador.
    return (bool) preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc);
}

function ensureFiel(): Fiel
{
    if (!isset($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase'])) {
        respond(['success' => false, 'message' => 'Sesión no autenticada (FIEL)'], 401);
    }
    try {
        $fiel = Fiel::create($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase']);
        if (! $fiel->isValid()) respond(['success' => false, 'message' => 'FIEL inválida o expirada'], 401);
        return $fiel;
    } catch (Throwable $e) {
        respond(['success' => false, 'message' => 'Error creando FIEL: ' . $e->getMessage()], 500);
    }
}

function serviceFromFiel(Fiel $fiel): Service
{
    $client = new Client(['timeout' => 90]);
    $webClient = new GuzzleWebClient($client);
    return new Service(new FielRequestBuilder($fiel), $webClient);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Sólo POST'], 405);
}
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    respond(['success' => false, 'message' => 'JSON inválido'], 400);
}
$tipo = strtolower($input['tipo'] ?? '');
if (!in_array($tipo, ['emitidas', 'recibidas'])) respond(['success' => false, 'message' => 'Tipo inválido'], 400);
$rfc = strtoupper(trim($input['rfc'] ?? ''));
$estatus = strtolower($input['estatus'] ?? 'vigentes');
$uuid = trim($input['uuid'] ?? '');
$formato = strtolower($input['formato'] ?? 'xml'); // xml | metadata
if (!in_array($formato, ['xml', 'metadata'])) $formato = 'xml';

if ($rfc === '') respond(['success' => false, 'message' => 'RFC requerido'], 400);
if (!validateRfc($rfc)) respond(['success' => false, 'message' => 'RFC con formato inválido'], 400);


$fiel = ensureFiel();

// Construir parámetros base
$parameters = QueryParameters::create()->withServiceType(ServiceType::cfdi());

// Estatus
$docStatus = match ($estatus) {
    'cancelados' => DocumentStatus::cancelled(),
    'todos' => DocumentStatus::undefined(),
    default => DocumentStatus::active(),

};
$parameters = $parameters->withDocumentStatus($docStatus);

    // Rango fechas necesario
    $fi = $input['fecha_inicio'] ?? '';
    $ff = $input['fecha_fin'] ?? '';
    if (!$fi || !$ff) respond(['success' => false, 'message' => 'Fechas requeridas'], 400);
    if ($fi > $ff) respond(['success' => false, 'message' => 'fecha_inicio > fecha_fin'], 400);
    $fiDT = new DateTimeImmutable($fi . ' 00:00:00');
    $ffDT = new DateTimeImmutable($ff . ' 23:59:59');
    $dias = $ffDT->diff($fiDT)->days;
    if ($dias > 31) respond(['success' => false, 'message' => 'Rango > 31 días'], 400);
    $period = DateTimePeriod::createFromValues($fiDT->format('Y-m-d H:i:s'), $ffDT->format('Y-m-d H:i:s'));
    $downloadType = ($tipo === 'recibidas') ? DownloadType::received() : DownloadType::issued();
    $reqType = ($formato === 'metadata') ? RequestType::metadata() : RequestType::xml();
    $parameters = $parameters->withPeriod($period)->withDownloadType($downloadType)->withRequestType($reqType);
    // RFC handling
    if ($tipo === 'emitidas') {
        $parameters = $parameters->withRfcOnBehalf(RfcOnBehalf::create($rfc));
    } else { // recibidas
        $parameters = $parameters->withRfcMatch(RfcMatch::create($rfc));
    }

$service = serviceFromFiel($fiel);
$result = $service->query($parameters);
$status = $result->getStatus();
if (! $status->isAccepted()) {
    respond(['success' => false, 'message' => 'Solicitud rechazada: ' . $status->getMessage(), 'status_code' => $status->getCode()], 400);
}

// Guardar registro
$db = (new Database())->getConnection();
$fiVal = $input['fecha_inicio'] ?? null;
$ffVal = $input['fecha_fin'] ?? null;
$rfcEmisor = null;
$rfcReceptor = null;

if ($tipo === 'emitidas') {
    $rfcEmisor = $rfc;
} elseif ($tipo === 'recibidas') {
    $rfcReceptor = $rfc;
}

// Prepara el insert con las columnas correctas
$ins = $db->prepare('INSERT INTO cf_solicitudes 
    (solicitud_id_sat, rfc_emisor, rfc_receptor, tipo, fecha_ini, fecha_fin, estado, paquetes_json) 
    VALUES (?,?,?,?,?,?,"pendiente",?)');

$ins->execute([
    $result->getRequestId(),
    $rfcEmisor,
    $rfcReceptor,
    $tipo,
    $fiVal,
    $ffVal,
    json_encode([]),
]);

$idLocal = (int)$db->lastInsertId();


respond([
    'success' => true,
    'requestId' => $result->getRequestId(),
    'id_solicitud' => $idLocal,
    'tipo' => $tipo,
    'rfc' => $rfc,
    'estatus_solicitado' => $estatus,
    'formato' => $formato,
    'status_code' => $status->getCode(),
    'status_message' => $status->getMessage(),
    'uuid' => $uuid ?: null,
    'dias_rango' => isset($dias) ? $dias : null,
]);
