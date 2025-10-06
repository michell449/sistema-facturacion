<?php
// app-m/core/cargar-cfdi-sat.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
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
    $autenticar = function () {
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
    };
} catch (\Throwable $e) {
    session_destroy();
    log_sat_activity("Error al recuperar FIEL de sesión", ['error' => $e->getMessage()]);
}
