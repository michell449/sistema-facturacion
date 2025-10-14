<?php
// core/test_descarga.php

// Ignorar advertencias sobre módulos ya cargados
error_reporting(E_ALL & ~E_WARNING);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use GuzzleHttp\Client;

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ser ejecutado desde la línea de comandos (CLI).");
}

// --- Argumentos de la línea de comandos ---
$rfc = (string) ($argv[1] ?? '');
$packageId = (string) ($argv[2] ?? '');

if ($rfc === '' || $packageId === '') {
    die(
        "ERROR: Faltan argumentos.\n\n" .
        "Uso: php core/test_descarga.php [RFC] [ID_PAQUETE]\n\n" .
        "Ejemplo: php core/test_descarga.php ADX220314QI2 D3EF69C1-E915-41D2-BADA-DB34981B7D1C_01\n"
    );
}

echo "--- INICIANDO DIAGNÓSTICO DE DESCARGA ---\n";
echo "RFC: " . $rfc . "\n";
echo "ID de Paquete: " . $packageId . "\n";
echo "-----------------------------------------\n\n";

try {
    // 1. Cargar la FIEL directamente desde los archivos
    // ¡ASEGÚRATE DE QUE LA RUTA Y LA CONTRASEÑA SEAN CORRECTAS!
    $fielPath = __DIR__ . '/../fieles/'; // Asume que tienes una carpeta 'fieles' en la raíz
    $cerFile = $fielPath . $rfc . '.cer';
    $keyFile = $fielPath . $rfc . '.key';
    $passphrase = 'jmvh25181927'; // <-- ¡IMPORTANTE! COLOCA AQUÍ TU CONTRASEÑA DE LA FIEL

    if (!file_exists($cerFile) || !file_exists($keyFile)) {
        die("ERROR: No se encontraron los archivos .cer o .key para el RFC {$rfc} en la carpeta 'fieles'.\n");
    }
    echo "1. Archivos de la FIEL encontrados.\n";

    $fiel = Fiel::create(
        file_get_contents($cerFile),
        file_get_contents($keyFile),
        $passphrase
    );

    if (!$fiel->isValid()) {
        die("ERROR: La FIEL es inválida, ha expirado o la contraseña es incorrecta.\n");
    }
    echo "2. La FIEL es válida.\n";

    // 2. Crear el servicio
    $client = new Client(['timeout' => 90, 'verify' => false]);
    $webClient = new GuzzleWebClient($client);
    $service = new Service(new FielRequestBuilder($fiel), $webClient);
    echo "3. Servicio del SAT creado.\n";

    // 3. Intentar la descarga
    echo "4. Intentando descargar el paquete del SAT...\n";
    $downloadResult = $service->download($packageId);
    $content = $downloadResult->getPackageContent();
    echo "5. ¡Descarga completada!\n\n";

    // 4. Analizar el contenido
    echo "--- ANÁLISIS DEL CONTENIDO RECIBIDO ---\n";
    $contentLength = strlen($content);
    echo "Tamaño del contenido: " . $contentLength . " bytes\n";

    // Guardar el contenido en un archivo de texto para inspección manual
    $outputFile = __DIR__ . '/../uploads/tmp/DIAGNOSTICO_' . $packageId . '.txt';
    @mkdir(__DIR__ . '/../uploads/tmp/', 0777, true); // Asegurarse de que el directorio existe
    file_put_contents($outputFile, $content);
    echo "El contenido exacto se ha guardado en: " . $outputFile . "\n\n";

    // Imprimir los primeros 512 caracteres para una vista rápida
    echo "Primeros 512 caracteres de la respuesta:\n";
    echo "****************************************\n";
    echo substr($content, 0, 512);
    echo "\n****************************************\n\n";

    if (strpos($content, 'PK') === 0 && $contentLength > 22) {
        echo "DIAGNÓSTICO: El archivo parece ser un ZIP, pero está corrupto. Revisa el archivo .txt para ver si es un ZIP parcial.\n";
    } elseif ($contentLength < 2000) {
        echo "DIAGNÓSTICO: El tamaño es muy pequeño. ¡Es un mensaje de error del SAT! Abre el archivo .txt para leerlo.\n";
    } else {
        echo "DIAGNÓSTICO: Contenido inesperado. Revisa el archivo .txt para analizarlo.\n";
    }

    echo "\n--- PRUEBA FINALIZADA ---\n";

} catch (Throwable $e) {
    echo "\n\nCRITICAL ERROR: Ocurrió una excepción durante el proceso.\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " en línea " . $e->getLine() . "\n";
}