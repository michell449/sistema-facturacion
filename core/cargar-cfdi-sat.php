<?php
// app-m/core/cargar-cfdi-sat.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use CfdiUtils\Cfdi;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceEndpoints;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\Exceptions\OpenZipFileException;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;

header('Content-Type: application/json; charset=utf-8');

function json_response($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function create_sat_service(Fiel $fiel): Service
{
    $requestBuilder = new FielRequestBuilder($fiel);
    $webClient = new GuzzleWebClient();
    return new Service($requestBuilder, $webClient);
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

// Intenta recuperar la Fiel desde la sesión si ya fue autenticada
$fiel = null;
if (isset($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase'])) {
    try {
        $fiel = Fiel::create(
            $_SESSION['fiel_cer_content'],
            $_SESSION['fiel_key_content'],
            $_SESSION['fiel_passphrase']
        );
    } catch (\Throwable $e) {
        // La fiel en sesión es inválida, limpiar sesión
        session_destroy();
    }
}

try {
    switch ($action) {
        // autenticar la e.firma
        case 'autenticar':
            if (
                empty($_FILES['cerFile']) || $_FILES['cerFile']['error'] !== UPLOAD_ERR_OK ||
                empty($_FILES['keyFile']) || $_FILES['keyFile']['error'] !== UPLOAD_ERR_OK ||
                empty($_POST['password'])
            ) {
                json_response(['success' => false, 'message' => 'Faltan archivos o contraseña, o hubo un error en la subida.'], 400);
            }

            $cerContent = file_get_contents($_FILES['cerFile']['tmp_name']);
            $keyContent = file_get_contents($_FILES['keyFile']['tmp_name']);
            $password = $_POST['password'];

            // Validar la e.firma y desencripta la clave privada
            try {
                $fielAutenticada = Fiel::create($cerContent, $keyContent, $password);
                if (!$fielAutenticada->isValid()) {
                    throw new \Exception('El certificado de la e.firma ha expirado o no es válido.');
                }
            } catch (\Throwable $e) {
                json_response(['success' => false, 'message' => 'La contraseña de la e.firma es incorrecta o los archivos están corruptos. Detalles: ' . $e->getMessage()], 401);
            }

            // Guardar el contenido de los archivos en sesión
            $_SESSION['fiel_cer_content'] = $cerContent;
            $_SESSION['fiel_key_content'] = $keyContent;
            $_SESSION['fiel_passphrase'] = $password;

            json_response(['success' => true, 'message' => 'Autenticación exitosa.', 'rfc' => $fielAutenticada->getRfc()]);
            break;

        // solicitar la descarga de CFDI
        case 'solicitar':
            if (!$fiel) {
                json_response(['success' => false, 'message' => 'Sesión no autenticada. Por favor, suba su e.firma de nuevo.'], 401);
            }

            // Validar estructura del JSON recibido
            if (!is_array($input) || !isset($input['fecha_inicio'], $input['fecha_fin'])) {
                json_response(['success' => false, 'message' => 'Datos de fecha no recibidos correctamente.'], 400);
            }

            $fechaInicioStr = $input['fecha_inicio'];
            $fechaFinStr = $input['fecha_fin'];

            if ($fechaInicioStr >= $fechaFinStr) {
                json_response(['success' => false, 'message' => 'La fecha de inicio debe ser menor que la fecha final.'], 400);
            }

            // Agrega horas para cubrir el día completo
            $fechaInicio = $fechaInicioStr . ' 00:00:00';
            $fechaFin = $fechaFinStr . ' 23:59:59';

            try {
                $period = DateTimePeriod::createFromValues($fechaInicio, $fechaFin);
            } catch (\Throwable $e) {
                json_response(['success' => false, 'message' => 'Error al procesar el rango de fechas: ' . $e->getMessage()], 400);
            }

            // Determinar el tipo de descarga
            $tipoDescarga = $input['tipo_descarga'] ?? 'recibidas';
            if (!in_array($tipoDescarga, ['recibidas', 'emitidas'])) {
                json_response(['success' => false, 'message' => 'Tipo de descarga inválido.'], 400);
            }

            $service = create_sat_service($fiel);

            $downloadType = ($tipoDescarga === 'recibidas')
                ? DownloadType::received()
                : DownloadType::issued();

            $parameters = QueryParameters::create()
                ->withPeriod($period)
                ->withDownloadType($downloadType)
                ->withDocumentStatus(DocumentStatus::active())
                ->withRequestType(RequestType::xml());

            $endpoints = ServiceEndpoints::cfdi();

            try {
                $result = $service->query($parameters, $endpoints);

                // Logging para depurar si es necesario
                error_log("Resultado de query al SAT:");
                error_log("Status code: " . $result->getStatus()->getCode());
                error_log("Mensaje: " . $result->getStatus()->getMessage());
                error_log("Request ID: " . $result->getRequestId());

                if (!$result->getStatus()->isAccepted()) {
                    json_response([
                        'success' => false,
                        'message' => 'Solicitud rechazada por el SAT: ' . $result->getStatus()->getMessage()
                    ], 500);
                }

                if (empty($result->getRequestId())) {
                    error_log("El SAT devolvió un requestId vacío.");
                    json_response(['success' => false, 'message' => 'El SAT no devolvió un ID de solicitud válido.'], 500);
                }

                // Guardar requestId en sesión si deseas usarlo luego
                $_SESSION['requestId'] = $result->getRequestId();

                json_response([
                    'success' => true,
                    'requestId' => $result->getRequestId(),
                    'message' => 'Solicitud enviada al SAT con éxito.'
                ]);
            } catch (\Throwable $e) {
                json_response([
                    'success' => false,
                    'message' => 'Error al consultar el SAT: ' . $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
            break;


        // verificar el estado de la solicitud
        case 'verificar':
            if (!$fiel) json_response(['success' => false, 'message' => 'Sesión no autenticada.'], 401);
            if (empty($input['requestId'])) json_response(['success' => false, 'message' => 'Falta el ID de la solicitud.'], 400);

            $service = create_sat_service($fiel);
            $result = $service->verify($input['requestId']);
            $status = $result->getStatus();
            $isFinished = !$status->isAccepted() || count($result->getPackagesIds()) > 0;

            json_response([
                'success' => true,
                'is_finished' => $isFinished,
                'status_code' => $status->getCode(),
                'message' => $status->getMessage(),
                'packageIds' => $result->getPackagesIds(),
            ]);
            break;

        // descargar un paquete 
        case 'descargar':
            if (!$fiel) json_response(['success' => false, 'message' => 'Sesión no autenticada.'], 401);
            if (empty($input['packageId'])) json_response(['success' => false, 'message' => 'Falta el ID del paquete.'], 400);

            $downloadDir = __DIR__ . '/../uploads/tmp/';
            if (!is_dir($downloadDir) && !mkdir($downloadDir, 0755, true)) {
                json_response(['success' => false, 'message' => 'No se pudo crear el directorio de descargas.'], 500);
            }

            $service = create_sat_service($fiel);
            $result = $service->download($input['packageId']);

            $zipFile = $downloadDir . $input['packageId'] . '.zip';
            file_put_contents($zipFile, $result->getPackageContent());

            json_response(['success' => true, 'message' => "Paquete " . $input['packageId'] . " descargado.", 'path' => $zipFile]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Acción no válida.'], 400);
            break;
    }
} catch (\Throwable $e) {
    json_response(['success' => false, 'message' => 'Error Crítico: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
}
