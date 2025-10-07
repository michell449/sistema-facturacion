<?php
// app-m/core/cargar-cfdi-sat.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceEndpoints;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;

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

function log_sat_activity($message, $data = null)
{
    $logFile = __DIR__ . '/../logs/sat_activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";

    if ($data !== null) {
        $logMessage .= " - " . json_encode($data);
    }

    $logMessage .= "\n";

    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);


$fiel = null;
if (isset($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase'])) {
    try {
        $fiel = Fiel::create(
            $_SESSION['fiel_cer_content'],
            $_SESSION['fiel_key_content'],
            $_SESSION['fiel_passphrase']
        );
    } catch (\Throwable $e) {
        session_destroy();
        log_sat_activity("Error al recuperar FIEL de sesión", ['error' => $e->getMessage()]);
    }
}

try {
    switch ($action) {
        case 'autenticar':
            if (
                empty($_FILES['cerFile']) || $_FILES['cerFile']['error'] !== UPLOAD_ERR_OK ||
                empty($_FILES['keyFile']) || $_FILES['keyFile']['error'] !== UPLOAD_ERR_OK ||
                empty($_POST['password'])
            ) {
                json_response(['success' => false, 'message' => 'Faltan archivos o contraseña.'], 400);
            }

            $cerContent = file_get_contents($_FILES['cerFile']['tmp_name']);
            $keyContent = file_get_contents($_FILES['keyFile']['tmp_name']);
            $password = $_POST['password'];

            try {
                $fielAutenticada = Fiel::create($cerContent, $keyContent, $password);
                if (!$fielAutenticada->isValid()) {
                    throw new \Exception('El certificado de la e.firma no es válido o ha expirado.');
                }
            } catch (\Throwable $e) {
                json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 401);
            }

            $_SESSION['fiel_cer_content'] = $cerContent;
            $_SESSION['fiel_key_content'] = $keyContent;
            $_SESSION['fiel_passphrase'] = $password;

            json_response(['success' => true, 'message' => 'Autenticación exitosa.', 'rfc' => $fielAutenticada->getRfc()]);
            break;

        case 'solicitar':
            if (!$fiel) {
                json_response(['success' => false, 'message' => 'Sesión no autenticada.'], 401);
            }

            if (!is_array($input) || !isset($input['fecha_inicio'], $input['fecha_fin'])) {
                log_sat_activity("Datos de fecha incompletos", $input);
                json_response(['success' => false, 'message' => 'Datos de fecha no recibidos correctamente.'], 400);
            }

            $fechaInicioStr = $input['fecha_inicio'];
            $fechaFinStr = $input['fecha_fin'];

            // Validaciones de fecha
            if ($fechaInicioStr >= $fechaFinStr) {
                json_response(['success' => false, 'message' => 'La fecha de inicio debe ser menor que la fecha final.'], 400);
            }

            // Limitar el rango a máximo 1 mes para evitar timeouts
            $fechaInicioDT = new DateTimeImmutable($fechaInicioStr . ' 00:00:00');
            $fechaFinDT = new DateTimeImmutable($fechaFinStr . ' 23:59:59');
            $diferenciaDias = $fechaFinDT->diff($fechaInicioDT)->days;

            if ($diferenciaDias > 31) {
                json_response(['success' => false, 'message' => 'El rango de fechas no puede ser mayor a 31 días para evitar timeouts del SAT.'], 400);
            }

            // Validar límite de 6 años
            $limiteInferior = (new DateTimeImmutable())->modify('-6 years')->setTime(0, 0, 0);
            if ($fechaInicioDT < $limiteInferior) {
                json_response(['success' => false, 'message' => 'La fecha de inicio es anterior al límite permitido (6 años).'], 400);
            }

            $period = DateTimePeriod::createFromValues(
                $fechaInicioDT->format('Y-m-d H:i:s'),
                $fechaFinDT->format('Y-m-d H:i:s')
            );

            $tipoDescarga = $input['tipo_descarga'] ?? 'recibidas';
            if (!in_array($tipoDescarga, ['recibidas', 'emitidas'])) {
                json_response(['success' => false, 'message' => 'Tipo de descarga inválido.'], 400);
            }

            $downloadType = ($tipoDescarga === 'recibidas')
                ? DownloadType::received()
                : DownloadType::issued();

            $parameters = QueryParameters::create()
                ->withPeriod($period)
                ->withDownloadType($downloadType)
                ->withDocumentStatus(DocumentStatus::active())
                ->withRequestType(RequestType::xml());

            $service = create_sat_service($fiel);

            log_sat_activity("Iniciando solicitud al SAT", [
                'fecha_inicio' => $fechaInicioStr,
                'fecha_fin' => $fechaFinStr,
                'tipo_descarga' => $tipoDescarga,
                'dias_rango' => $diferenciaDias
            ]);

            $result = $service->query($parameters, ServiceEndpoints::cfdi());

            log_sat_activity("Respuesta de solicitud SAT", [
                'status_code' => $result->getStatus()->getCode(),
                'status_message' => $result->getStatus()->getMessage(),
                'request_id' => $result->getRequestId()
            ]);

            if (!$result->getStatus()->isAccepted()) {
                json_response([
                    'success' => false,
                    'message' => 'Solicitud rechazada: ' . $result->getStatus()->getMessage()
                ], 500);
            }

            $_SESSION['requestId'] = $result->getRequestId();
            $_SESSION['fecha_inicio'] = $fechaInicioStr;
            $_SESSION['fecha_fin'] = $fechaFinStr;

            json_response([
                'success' => true,
                'requestId' => $result->getRequestId(),
                'message' => 'Solicitud enviada al SAT con éxito.',
                'dias_rango' => $diferenciaDias
            ]);

            $stmt = $conn->prepare("INSERT INTO sat_solicitudes (solicitudes_id, tipo_solicitud, fecha_inicio, fecha_fin, estado) VALUES (?, ?, ?, ?, 'pendiente')");
            $stmt->bind_param("ssss", $result->getRequestId(), $tipoDescarga, $fechaInicioStr, $fechaFinStr);
            $stmt->execute();

            json_response([
                "success" => true,
                "message" => "Solicitud enviada al SAT. Se procesará en segundo plano."
            ]);

            break;

        case 'verificar':
            if (!$fiel) {
                json_response(['success' => false, 'message' => 'Sesión no autenticada.'], 401);
            }

            if (empty($input['requestId'])) {
                if (empty($_SESSION['requestId'])) {
                    json_response(['success' => false, 'message' => 'Falta el ID de la solicitud.'], 400);
                }
                $requestId = $_SESSION['requestId'];
            } else {
                $requestId = $input['requestId'];
            }

            log_sat_activity("Verificando solicitud", ['request_id' => $requestId]);

            $service = create_sat_service($fiel);
            $result = $service->verify($requestId);

            $status = $result->getStatus();
            $packageIds = $result->getPackagesIds();

            // Mejor lógica para determinar si está terminado
            $isFinished = false;
            $statusCode = $status->getCode();

            // Códigos que indican que la solicitud terminó
            $codigosTerminados = [5000, 5001, 5002, 5003, 5004];

            if (in_array($statusCode, $codigosTerminados)) {
                // Si tiene paquetes, está terminado y listo
                if (count($packageIds) > 0) {
                    $isFinished = true;
                }
                // Si no tiene paquetes pero el mensaje indica terminado
                elseif (
                    strpos(strtolower($status->getMessage()), 'terminado') !== false ||
                    strpos(strtolower($status->getMessage()), 'finalizado') !== false ||
                    strpos(strtolower($status->getMessage()), 'completado') !== false
                ) {
                    $isFinished = true;
                }
                // Si es código 5000 pero ha pasado mucho tiempo, forzar como terminado
                elseif ($statusCode === 5000) {
                    // Verificar si ha pasado más de 10 minutos desde la solicitud
                    if (isset($_SESSION['solicitud_timestamp'])) {
                        $tiempoTranscurrido = time() - $_SESSION['solicitud_timestamp'];
                        if ($tiempoTranscurrido > 600) { // 10 minutos
                            $isFinished = true;
                            log_sat_activity("Forzando terminación por timeout", [
                                'request_id' => $requestId,
                                'tiempo_transcurrido' => $tiempoTranscurrido
                            ]);
                        }
                    }
                }
            }

            log_sat_activity("Resultado de verificación", [
                'request_id' => $requestId,
                'status_code' => $statusCode,
                'status_message' => $status->getMessage(),
                'is_finished' => $isFinished,
                'package_count' => count($packageIds),
                'package_ids' => $packageIds
            ]);

            json_response([
                'success' => true,
                'is_finished' => $isFinished,
                'status_code' => $statusCode,
                'message' => $status->getMessage(),
                'packageIds' => $packageIds,
                'requestId' => $requestId,
                'has_packages' => count($packageIds) > 0
            ]);
            break;

        case 'descargar':
            if (!$fiel) {
                json_response(['success' => false, 'message' => 'Sesión no autenticada.'], 401);
            }

            if (empty($input['packageId'])) {
                json_response(['success' => false, 'message' => 'Falta el ID del paquete.'], 400);
            }

            $downloadDir = __DIR__ . '/../uploads/tmp/';
            if (!is_dir($downloadDir)) {
                mkdir($downloadDir, 0755, true);
            }

            log_sat_activity("Descargando paquete", ['package_id' => $input['packageId']]);

            $service = create_sat_service($fiel);
            $result = $service->download($input['packageId']);

            if ($result->getStatus()) {
                log_sat_activity("Paquete no encontrado", ['package_id' => $input['packageId']]);
                json_response(['success' => false, 'message' => 'El paquete no fue encontrado en el SAT.'], 404);
            }

            if (!$result->getStatus()->isAccepted()) {
                log_sat_activity("Error al descargar paquete", [
                    'package_id' => $input['packageId'],
                    'status' => $result->getStatus()->getMessage()
                ]);
                json_response(['success' => false, 'message' => 'Error al descargar: ' . $result->getStatus()->getMessage()], 500);
            }

            $zipFile = $downloadDir . $input['packageId'] . '.zip';
            $bytesEscritos = file_put_contents($zipFile, $result->getPackageContent());

            if ($bytesEscritos === false) {
                log_sat_activity("Error al escribir archivo ZIP", ['package_id' => $input['packageId']]);
                json_response(['success' => false, 'message' => 'Error al guardar el archivo en el servidor.'], 500);
            }

            log_sat_activity("Paquete descargado exitosamente", [
                'package_id' => $input['packageId'],
                'file' => basename($zipFile),
                'size' => filesize($zipFile),
                'bytes_escritos' => $bytesEscritos
            ]);

            json_response([
                'success' => true,
                'message' => 'Descarga completada.',
                'file' => basename($zipFile),
                'packageId' => $input['packageId'],
                'file_size' => filesize($zipFile)
            ]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Acción no reconocida.'], 400);
    }
} catch (\Throwable $e) {
    log_sat_activity("Error general", [
        'action' => $action,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
