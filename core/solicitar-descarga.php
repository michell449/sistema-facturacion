<?php
// app-m\core\solicitar-descarga.php
header('Content-Type: application/json; charset=utf-8');

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

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use GuzzleHttp\Client;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcMatch;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcOnBehalf;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceType;

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validateRfc(string $rfc): bool
{
    return (bool) preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc);
}

function ensureFiel(): Fiel
{
    if (!isset($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase'])) {
        respond(['success' => false, 'message' => 'Sesión no autenticada (FIEL)'], 401);
    }

    try {
        $fiel = Fiel::create($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase']);
        if (!$fiel->isValid()) {
            respond(['success' => false, 'message' => 'FIEL inválida o expirada'], 401);
        }
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
    respond(['success' => false, 'message' => 'Sólo POST permitido'], 405);
}

// Leer entrada JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['success' => false, 'message' => 'JSON inválido'], 400);
}

$tipo = $input['tipo'] ?? '';
if (!in_array($tipo, ['emitidas', 'recibidas'])) {
    respond(['success' => false, 'message' => 'Tipo inválido'], 400);
}

$rfc = strtoupper(trim($input['rfc'] ?? ''));
if ($rfc === '' || !validateRfc($rfc)) {
    respond(['success' => false, 'message' => 'RFC inválido'], 400);
}

$estatus = strtolower($input['estatus'] ?? 'vigentes');
$fi = $input['fecha_inicio'] ?? '';
$ff = $input['fecha_fin'] ?? '';
if (!$fi || !$ff) respond(['success' => false, 'message' => 'Fechas requeridas'], 400);
if ($fi > $ff) respond(['success' => false, 'message' => 'fecha_inicio > fecha_fin'], 400);

// Crear FIEL
$fiel = ensureFiel();

// Crear parámetros SAT
$parameters = QueryParameters::create()->withServiceType(ServiceType::cfdi());
$docStatus = match ($estatus) {
    'cancelados' => DocumentStatus::cancelled(),
    'todos' => DocumentStatus::undefined(),
    default => DocumentStatus::active(),
};

$fiDT = new DateTimeImmutable($fi . ' 00:00:00');
$ffDT = new DateTimeImmutable($ff . ' 23:59:59');
$dias = $ffDT->diff($fiDT)->days;
if ($dias > 31) respond(['success' => false, 'message' => 'El rango supera 31 días'], 400);

$period = DateTimePeriod::createFromValues($fiDT->format('Y-m-d H:i:s'), $ffDT->format('Y-m-d H:i:s'));
$downloadType = ($tipo === 'recibidas') ? DownloadType::received() : DownloadType::issued();

$parameters = $parameters
    ->withDocumentStatus($docStatus)
    ->withPeriod($period)
    ->withDownloadType($downloadType);

if ($tipo === 'emitidas') {
    $parameters = $parameters->withRfcOnBehalf(RfcOnBehalf::create($rfc));
} else {
    $parameters = $parameters->withRfcMatch(RfcMatch::create($rfc));
}

// Enviar solicitud SAT
$service = serviceFromFiel($fiel);
$result = $service->query($parameters);
$status = $result->getStatus();

if (!$status->isAccepted()) {
    respond(['success' => false, 'message' => 'Solicitud rechazada: ' . $status->getMessage(), 'status_code' => $status->getCode()], 400);
}


// Guardar en base de datos
try {
    $db = (new Database())->getConnection();

    // Fecha actual desde PHP
    $ahora = date('Y-m-d H:i:s');

    $stmt = $db->prepare("
        INSERT INTO cf_solicitudes (
            solicitud_id_sat,
            fecha_creacion,
            ultima_verificacion,
            estado,
            tipo,
            folio,
            fecha_ini,
            fecha_fin,
            total_paquetes,
            total_cfdis,
            paquetes_json,
            mensaje_error,
            rfc_emisor,
            rfc_receptor
        ) VALUES (
            ?, NOW(), ?, 'pendiente', ?, NULL, ?, ?, 0, 0, ?, NULL, ?, ?
        )
    ");

    $rfcEmisor   = ($tipo === 'emitidas') ? $rfc : null;
    $rfcReceptor = ($tipo === 'recibidas') ? $rfc : null;

    $stmt->execute([
        $result->getRequestId(), 
        $ahora,                  
        $tipo,       
        $fi,              
        $ff,              
        json_encode([]),      
        $rfcEmisor,        
        $rfcReceptor         
    ]);

    $idLocal = $db->lastInsertId();

    respond([
        'success' => true,
        'message' => 'Solicitud registrada correctamente. El SAT procesará la solicitud.',
        'id_solicitud' => $idLocal,
        'requestId' => $result->getRequestId(),
        'tipo' => $tipo,
        'rfc' => $rfc,
        'estatus_solicitado' => $estatus,
        'dias_rango' => $dias,
        'status_code' => $status->getCode(),
        'status_message' => $status->getMessage()
    ]);

} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Error guardando en BD: ' . $e->getMessage()], 500);
}

$rfcCol = $has('rfc') ? 'rfc' : ($has('rfc_emisor') ? 'rfc_emisor' : ($has('rfc_receptor') ? 'rfc_receptor' : 'NULL'));
$sql = "SELECT id_solicitud, solicitud_id_sat, $rfcCol AS rfc, ... FROM cf_solicitudes";
