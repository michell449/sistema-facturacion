<?php
// app-m/core/autenticar-sat.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\Token;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;

header('Content-Type: application/json; charset=utf-8');

function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function log_sat_activity($message, $data = null) {
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

// Validar archivos y contraseña
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
    //Creacion y validacion de la FIEL
    $fiel = Fiel::create($cerContent, $keyContent, $password);

    if (!$fiel->isValid()) {
        throw new \Exception('El certificado de la e.firma no es válido o ha expirado.');
    }

    $rfc = $fiel->getRfc();

    // inicializar el servicio del SAT
    $webClient = new GuzzleWebClient();
    $requestBuilder = new FielRequestBuilder($fiel);
    $service = new Service($requestBuilder, $webClient); // Se crea el servicio para CFDI Regulares por defecto

    // obtener el token de autenticación
    log_sat_activity("Iniciando solicitud de autenticación al SAT", ['rfc' => $rfc]);
    $token = $service->authenticate(); 

    // Verificar que el token no esté vacío
    if ($token->isValueEmpty()) {
        throw new \Exception('El SAT no devolvió un token de autenticación. Posiblemente un error de servicio.');
    }

    // Almacenar datos en la sesión
    if (!isset($_SESSION['sat_data'])) {
        $_SESSION['sat_data'] = [];
    }

    $_SESSION['sat_data'][$rfc]['fiel_credentials'] = [
        'cer_content' => $cerContent,
        'key_content' => $keyContent,
        'passphrase' => $password,
    ];

    // Almacena la información del token 
    $_SESSION['sat_data'][$rfc]['token_data'] = [
        'value' => $token->getValue(),
        'created' => $token->getCreated()->format('Y-m-d H:i:s'),
        'expires' => $token->getExpires()->format('Y-m-d H:i:s'),
    ];

    log_sat_activity("Autenticación exitosa y Token obtenido", ['rfc' => $rfc, 'expires' => $token->getExpires()->format('Y-m-d H:i:s')]);

    json_response([
        'success' => true,
        'message' => 'Autenticación exitosa y Token obtenido.',
        'rfc' => $rfc,
        'token_expires' => $token->getExpires()->format('Y-m-d H:i:s')
    ]);

} catch (WebClientException $e) {
    log_sat_activity("Error de WebClient durante la autenticación", ['error' => $e->getMessage(), 'request' => $e->getRequest()->jsonSerialize(), 'response' => $e->getResponse()->jsonSerialize()]);
    json_response(['success' => false, 'message' => 'Error de comunicación con el SAT: ' . $e->getMessage()], 503);
} catch (\Throwable $e) {
    log_sat_activity("Error autenticando FIEL o iniciando servicio", ['error' => $e->getMessage()]);
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 401);
}