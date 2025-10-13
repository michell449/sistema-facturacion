<?php
// core/solicitar-descarga.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php'; // Asegura que la sesión esté iniciada
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use GuzzleHttp\Client;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

// --- Funciones de Ayuda ---

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
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

function createService(Fiel $fiel): Service
{
    $client = new Client(['timeout' => 45]);
    $webClient = new GuzzleWebClient($client);
    return new Service(new FielRequestBuilder($fiel), $webClient);
}


// --- Lógica Principal ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

// 1. Validar campos básicos
$requiredFields = ['fecha_inicio', 'fecha_fin', 'tipo_descarga', 'rfc'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        respond(['success' => false, 'message' => "El campo '$field' es obligatorio."], 400);
    }
}

// 2. Validar que las fechas no sean futuras.
try {
    $hoy = new \DateTimeImmutable('now', new \DateTimeZone('America/Mexico_City'));
    $inicio = new \DateTimeImmutable($input['fecha_inicio']);
    $fin = new \DateTimeImmutable($input['fecha_fin']);

    if ($inicio > $hoy) {
        respond(['success' => false, 'message' => 'La fecha de inicio no puede ser una fecha futura.'], 400);
    }
    if ($fin > $hoy) {
        respond(['success' => false, 'message' => 'La fecha de fin no puede ser una fecha futura.'], 400);
    }
    if ($inicio > $fin) {
        respond(['success' => false, 'message' => 'La fecha de inicio no puede ser posterior a la fecha de fin.'], 400);
    }
} catch (\Exception $e) {
    respond(['success' => false, 'message' => 'Las fechas proporcionadas no tienen un formato válido.'], 400);
}

$tipoSolicitud = 'cfdi';

// 3. Limpiar y validar rigurosamente el tipo de descarga.
$tipoDescarga = isset($input['tipo_descarga']) ? strtolower(trim($input['tipo_descarga'])) : '';

$rfcSolicitante = $input['rfc'];
$rfcEmisor = '';
$rfcReceptor = '';
$rfcAutenticacion = $rfcSolicitante; 

if ($tipoDescarga === 'emitidos' || $tipoDescarga === 'emitidas') {
    $rfcEmisor = $rfcSolicitante;
    $tipoDescarga = 'emitidos'; // Se normaliza a 'emitidos'
} elseif ($tipoDescarga === 'recibidos' || $tipoDescarga === 'recibidas') {
    $rfcReceptor = $rfcSolicitante;
    $tipoDescarga = 'recibidos'; // Se normaliza a 'recibidos'
} else {
    $receivedValue = $input['tipo_descarga'] ?? '[NO SE RECIBIÓ VALOR]';
    respond(['success' => false, 'message' => "El tipo de descarga no es válido. Se recibió el valor: '" . $receivedValue . "'"], 400);
}

// 4. Cargar la FIEL de la sesión
$fiel = getFielFromSource($rfcAutenticacion);
if (!$fiel) {
    respond(['success' => false, 'message' => 'No se pudo cargar la FIEL para el RFC ' . $rfcAutenticacion . '. Por favor, autentíquese primero.'], 401);
}

try {
    $service = createService($fiel);

    $start = DateTime::create($input['fecha_inicio'] . ' 00:00:00');
    $end = DateTime::create($input['fecha_fin'] . ' 23:59:59');
    $period = new DateTimePeriod($start, $end);

    $downloadType = ($tipoDescarga === 'recibidos') ? DownloadType::received() : DownloadType::issued();
   
    
    $requestType = RequestType::cfdi();

    $queryParams = \PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters::create(
        $period,
        $downloadType,
        $requestType
    );

    $queryResult = $service->query($queryParams);
    $status = $queryResult->getStatus();

    if (!$status->isAccepted()) {
        throw new \Exception("La solicitud fue rechazada por el SAT: [{$status->getCode()}] {$status->getMessage()}");
    }

    $db = (new Database())->getConnection();
    $stmt = $db->prepare(
        'INSERT INTO cf_solicitudes (solicitud_id_sat, fecha_solicitud, fecha_inicial, fecha_final, rfc_emisor, rfc_receptor, tipo_solicitud, estado, mensaje)
         VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $queryResult->getRequestId(),
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $rfcEmisor ?: null,
        $rfcReceptor ?: null,
        $tipoSolicitud,
        'aceptada',
        $status->getMessage()
    ]);
    
    $idSolicitud = $db->lastInsertId();

    respond([
        'success' => true,
        'message' => 'Solicitud enviada correctamente al SAT.',
        'id_solicitud_local' => $idSolicitud,
        'id_solicitud_sat' => $queryResult->getRequestId(),
        'estado_sat' => [
            'code' => $status->getCode(),
            'message' => $status->getMessage()
        ]
    ]);

} catch (\Throwable $e) {
    error_log("Error en solicitar-descarga.php: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
}