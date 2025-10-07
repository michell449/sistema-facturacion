<?php
// app-m/core/autenticar-sat.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;

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
    $fiel = Fiel::create($cerContent, $keyContent, $password);

    if (!$fiel->isValid()) {
        throw new \Exception('El certificado de la e.firma no es válido o ha expirado.');
    }

    // Guardar FIEL en sesión
    $_SESSION['fiel_cer_content'] = $cerContent;
    $_SESSION['fiel_key_content'] = $keyContent;
    $_SESSION['fiel_passphrase'] = $password;

    log_sat_activity("FIEL autenticada correctamente", ['rfc' => $fiel->getRfc()]);

    json_response([
        'success' => true,
        'message' => 'Autenticación exitosa.',
        'rfc' => $fiel->getRfc()
    ]);

} catch (\Throwable $e) {
    log_sat_activity("Error autenticando FIEL", ['error' => $e->getMessage()]);
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 401);
}
