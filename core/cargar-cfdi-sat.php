<?php
// app-m/core/cargar-cfdi-sat.php
session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../vendor/autoload.php";

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceEndpoints;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

header('Content-Type: application/json; charset=utf-8');

function json_response($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    json_response(['success' => false, 'message' => 'No autenticado'], 401);
}


$action = $_GET['action'] ?? '';
$imput = json_decode(file_get_contents('php://input'), true);
$fiel = null;
if (isset($_SESSION['fiel_rfc'], $_SESSION['fiel_key_path'], $_SESSION['fiel_cer_path'], $_SESSION['fiel_passphrase'])) {
    if (file_exists($_SESSION['fiel_key_path']) && file_exists($_SESSION['fiel_cer_path'])) {
        $fiel = Fiel::create(file_get_contents($_SESSION['fiel_cer_path']), file_get_contents($_SESSION['fiel_key_path']), $_SESSION['fiel_passphrase']);
    }
}
if (!$fiel) {
    json_response(['success' => false, 'message' => 'FIEL no válida o no proporcionada'], 400);
}


try{
    switch ($action){
        case 'Autenticar':
            if (empty($_FILES['cer_file']) || empty($_FILES['key_file']) || empty($_POST['password'])) {
                json_response(['success' => false, 'message' => 'Faltan archivos o contraseña'], 400);
            }
            //Mover los achivos a una ubicación temporal
            $uploadDir = __DIR__ . "/../uploads/efirma_tmp/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $cerPath = $uploadDir . session_id() . ".cer";
            $keyPath = $uploadDir . session_id() . ".key";
            if (!move_uploaded_file($_FILES['cer_file']['tmp_name'], $cerPath) || !move_uploaded_file($_FILES['key_file']['tmp_name'], $keyPath)) {
                json_response(['success' => false, 'message' => 'Error al cargar la e.firma'], 500);
            }
            // validar la fiel 
            try {
                $fiel = Fiel::create(file_get_contents($cerPath), file_get_contents($keyPath), $_POST['password']);
                if (!$fiel->isValid()) {
                    throw new \Exception('La e.firma no es válida');
                }
            } catch (\Throwable $e) {
                // Eliminar archivos temporales
                if (file_exists($cerPath)) unlink($cerPath);
                if (file_exists($keyPath)) unlink($keyPath);
                json_response(['success' => false, 'message' => 'Error al validar la e.firma: ' . $e->getMessage()], 400);
            }
            $_SESSION['fiel_rfc'] = $fiel->getRfc();
            $_SESSION['fiel_cer_path'] = $cerPath;
            $_SESSION['fiel_key_path'] = $keyPath;
            $_SESSION['fiel_passphrase'] = $_POST['password'];
            json_response(['success' => true, 'message' => 'Autenticacion Exitosa', 'rfc' => $fiel->getRfc()]);
            break;
        case 'Descargar':
            if (!$fiel) json_response(['success' => false, 'message' => 'Aun no se ha autenticado, suba su e.firma'], 400);  
            $startDate = new DateTimeImmutable($imput['fecha_inicio']);
            $endDate = new DateTimeImmutable($imput['fecha_fin']);
            $tipoDescarga = $imput['tipo_descarga'] ?? 'recibidos'; // recibidos o emitidos
            $endpoints = ($tipoDescarga === 'recibidos') ? ServiceEndpoints::cfdi() : ServiceEndpoints::retenciones();
            $requestBuilder = new FielRequestBuilder($fiel);
            $webClient = new GuzzleWebClient();
            $service = new Service($requestBuilder, $webClient, null, $endpoints);
            $parameters = \PhpCfdi\SatWsDescargaMasiva\Shared\RequestType::create() ->withPeriod($startDate, $endDate) ->withDownloadType(\PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType::cfdi());
            if ($tipoDescarga === 'recibidos') { $parameters = $parameters->withRequestType(\PhpCfdi\SatWsDescargaMasiva\Shared\RequestType::recibidos()); 
            }
            else { $parameters = $parameters->withRequestType(\PhpCfdi\SatWsDescargaMasiva\Shared\RequestType::emitidos()); 
            }
            $result = $service->query($parameters);
            if (!$result->getStatus()->isAccepted()) {
                json_response(['success' => false, 'message' => 'Error en la consulta: ' . $result->getStatus()->getMessage()], 500);
            }
            $_SESSION['requestId'] = $result->getRequestId(); 
                        json_response(['success' => true, 'requestId' => $result->getRequestId(), 'message' => 'Solicitud enviada al SAT con éxito.']); 
                        break; 
        case 'Verificar':
            if (!$fiel) json_response(['success' => false, 'message' => 'No autenticado.'], 401);
            if (empty($input['requestId'])) json_response(['success' => false, 'message' => 'Falta el ID de la solicitud.'], 400);

            $requestId = $input['requestId'];
            $requestBuilder = new FielRequestBuilder($fiel);
            $service = new Service($requestBuilder, new GuzzleWebClient());

            $result = $service->download($packageId);
            $zipFile = $downloadDir . $packageId . '.zip';
            file_put_contents($zipFile, $result->getPackageContent());
            
            json_response(['success' => true, 'message' => "Paquete $packageId descargado.", 'path' => $zipFile]);
            break;
        case 'Descargar':
            if (!$fiel) json_response(['success' => false, 'message' => 'No autenticado.'], 401);
            if (empty($input['packageId'])) json_response(['success' => false, 'message' => 'Falta el ID del paquete.'], 400);

            $packageId = $input['packageId'];
            $downloadDir = __DIR__ . '/../uploads/sat_packages/';
            if (!is_dir($downloadDir)) mkdir($downloadDir, 0755, true);
            $requestBuilder = new FielRequestBuilder($fiel);
            $service = new Service($requestBuilder, new GuzzleWebClient());
            $result = $service->download($packageId);
            
            $zipFile = $downloadDir . $packageId . '.zip';
            file_put_contents($zipFile, $result->getPackageContent());

            json_response(['success' => true, 'message' => "Paquete $packageId descargado.", 'path' => $zipFile]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Acción no válida.'], 400);
            break;
    }
} catch (\Throwable $e) {
    json_response(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()], 500);
}
