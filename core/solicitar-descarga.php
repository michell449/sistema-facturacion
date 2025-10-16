<?php
// core/solicitar-descarga.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime; 
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceType;
use PhpCfdi\SatWsDescargaMasiva\Shared\Token;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use GuzzleHttp\Client;

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Carga la Fiel y el Token de la sesión y crea una instancia de Service.
function getServiceInstance(string $rfc): ?Service
{
    if (!isset($_SESSION['sat_data'][$rfc]['fiel_credentials'])) {
        return null;
    }

    $fielData = $_SESSION['sat_data'][$rfc]['fiel_credentials'];
    $tokenData = $_SESSION['sat_data'][$rfc]['token_data'] ?? null;
    $token = null;

    try {
        $fiel = Fiel::create(
            $fielData['cer_content'],
            $fielData['key_content'],
            $fielData['passphrase']
        );
        if (!$fiel->isValid()) {
            error_log("La FIEL para $rfc ya no es válida.");
            return null;
        }
    } catch (\Throwable $e) {
        error_log("Error al crear la FIEL desde la sesión para $rfc: " . $e->getMessage());
        return null;
    }

    if ($tokenData) {
        try {
            $token = new Token(
                DateTime::create($tokenData['created']),
                DateTime::create($tokenData['expires']),
                $tokenData['value']
            );
        } catch (\Throwable $e) {
            error_log("Error al recrear el Token para $rfc: " . $e->getMessage());
            $token = null;
        }
    }
    
    $webClient = new GuzzleWebClient(new Client(['timeout' => 45]));
    $requestBuilder = new FielRequestBuilder($fiel);

    return new Service($requestBuilder, $webClient, $token);
}


// Guarda el Token de autenticación en la sesión
function saveTokenToSession(string $rfc, Token $token): void
{
    $_SESSION['sat_data'][$rfc]['token_data'] = [
        'value' => $token->getValue(),
        'created' => $token->getCreated()->format('Y-m-d H:i:s'),
        'expires' => $token->getExpires()->format('Y-m-d H:i:s'),
    ];
}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

// Validar campos 
$requiredFields = ['fecha_inicio', 'fecha_fin', 'tipo_descarga', 'rfc'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        respond(['success' => false, 'message' => "El campo '$field' es obligatorio."], 400);
    }
}

// Validar fechas
try {
    $hoy = DateTime::now();
    $inicio = DateTime::create($input['fecha_inicio'] . ' 00:00:00');
    $fin = DateTime::create($input['fecha_fin'] . ' 23:59:59');

    if ($inicio->compareTo($hoy) > 0) {
        respond(['success' => false, 'message' => 'La fecha de inicio no puede ser una fecha futura.'], 400);
    }
    if ($fin->compareTo($hoy) > 0) {
        respond(['success' => false, 'message' => 'La fecha de fin no puede ser una fecha futura.'], 400);
    }
    $period = DateTimePeriod::create($inicio, $fin);

} catch (\Exception $e) {
    $message = $e->getMessage() === 'The final date must be greater than the initial date' 
        ? 'La fecha de inicio no puede ser posterior o igual a la fecha de fin.' 
        : 'Las fechas proporcionadas no tienen un formato válido.';
        
    respond(['success' => false, 'message' => $message], 400);
}

$tipoSolicitud = 'cfdi';
$tipoDescarga = isset($input['tipo_descarga']) ? strtolower(trim($input['tipo_descarga'])) : '';

$rfcSolicitante = $input['rfc'];
$rfcEmisor = '';
$rfcReceptor = '';
$rfcAutenticacion = $rfcSolicitante;

if ($tipoDescarga === 'emitidos' || $tipoDescarga === 'emitidas') {
    $rfcEmisor = $rfcSolicitante;
    $tipoDescarga = 'issued';
    $tipoSolicitudBD = 'emitidas';
} elseif ($tipoDescarga === 'recibidos' || $tipoDescarga === 'recibidas') {
    $rfcReceptor = $rfcSolicitante;
    $tipoDescarga = 'received';
    $tipoSolicitudBD = 'recibidas';
} else {
    $receivedValue = $input['tipo_descarga'] ?? '[NO SE RECIBIÓ VALOR]';
    respond(['success' => false, 'message' => "El tipo de descarga no es válido. Se recibió el valor: '" . $receivedValue . "'"], 400);
}


// Cargar el Service con la FIEL y el Token de la sesión
$service = getServiceInstance($rfcAutenticacion);
if (!$service) {
    respond(['success' => false, 'message' => 'No se pudo cargar la FIEL para el RFC ' . $rfcAutenticacion . '. Por favor, autentíquese primero.'], 401);
}

// Verificar si ya existe una solicitud similar
$db = (new Database())->getConnection();
$checkStmt = $db->prepare(
    'SELECT id_solicitud, estado FROM cf_solicitudes WHERE fecha_ini = ? AND fecha_fin = ? AND tipo = ? AND (rfc_emisor = ? OR rfc_receptor = ?)'
);
$checkStmt->execute([
    $inicio->format('Y-m-d'),
    $fin->format('Y-m-d'),
    $tipoSolicitudBD,
    $rfcSolicitante,
    $rfcSolicitante
]);
$existingRequest = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($existingRequest && in_array($existingRequest['estado'], ['aceptada', 'en_proceso', 'terminada'], true)) {
    respond([
        'success' => false,
        'message' => 'Ya existe una solicitud para este período y tipo de descarga. Por favor, verifique sus solicitudes existentes.'
    ], 409);
}


try {
    $downloadType = ($tipoDescarga === 'received') ? DownloadType::received() : DownloadType::issued();
    $requestType = RequestType::xml(); 


    $queryParams = QueryParameters::create($period)
        ->withDownloadType($downloadType)
        ->withRequestType($requestType)
        ->withServiceType(ServiceType::cfdi());

    // enviar solicitud de consulta
    $queryResult = $service->query($queryParams);
    $status = $queryResult->getStatus();

    saveTokenToSession($rfcAutenticacion, $service->getToken());

    //  estados de solicitud
    if (!$status->isAccepted()) {
        $message = "La solicitud fue rechazada por el SAT: [{$status->getCode()}] {$status->getMessage()}";
        $code = 400;

        switch ($status->getCode()) {
            case 300:
                $message = "Usuario No Válido: {$status->getMessage()}";
                $code = 401; 
                break;
            case 301:
                $message = "XML Mal Formado: {$status->getMessage()}";
                break;
            case 304:
            case 305:
                $message = "Certificado Inválido, Revocado o Caduco: {$status->getMessage()}";
                $code = 401; 
                break;
            case 5002:
                $message = "Se han agotado las solicitudes de por vida para este criterio: {$status->getMessage()}";
                $code = 429; 
                break;
            case 5005:
                $message = "Ya existe una solicitud registrada con los mismos criterios: {$status->getMessage()}";
                $code = 409;
                break;
        }
        respond(['success' => false, 'message' => $message], $code);
    }
    
    // registra solicitud en la base de datos
    $stmt = $db->prepare(
        'INSERT INTO cf_solicitudes (
            solicitud_id_sat, fecha_creacion, estado, tipo, folio, fecha_ini, fecha_fin, 
            rfc_emisor, rfc_receptor, token
        ) VALUES (
            ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?
        )'
    );

    $tokenInternal = bin2hex(random_bytes(10));

    $stmt->execute([
        $queryResult->getRequestId(), 
        'aceptada',         
        $tipoSolicitudBD,      
        $status->getMessage(), // El campo 'folio' parece usarse para el mensaje de estado del SAT
        $inicio->format('Y-m-d'),   
        $fin->format('Y-m-d'),     
        $rfcEmisor ?: null,
        $rfcReceptor ?: null,          
        $tokenInternal 
    ]);

    $idSolicitud = $db->lastInsertId();

    respond([
        'success' => true,
        'message' => 'Solicitud enviada correctamente al SAT y registrada.',
        'id_solicitud_local' => $idSolicitud,
        'id_solicitud_sat' => $queryResult->getRequestId(),
        'estado_sat' => [
            'code' => $status->getCode(),
            'message' => $status->getMessage()
        ]
    ]);

} catch (WebClientException $e) {
    error_log("Error de WebClient: " . $e->getMessage() . " - Body: " . $e->getResponse()->getBody());
    respond(['success' => false, 'message' => 'Error de comunicación con el SAT: ' . $e->getMessage()], 503);
} catch (\Throwable $e) {
    error_log("Error en solicitar-descarga.php: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
}