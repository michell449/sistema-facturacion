<?php
// Forzar la captura de cualquier salida inesperada desde el inicio.
ob_start();

// Solo permitir peticiones POST
if ($_SERVER!== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

require_once __DIR__. "/../config.php";

ini_set('display_errors', 0); // Crucial para no contaminar la respuesta JSON
ini_set('log_errors', 1);
libxml_use_internal_errors(true);

$uploadTmpDir = __DIR__. "/../uploads/tmp/";
if (!is_dir($uploadTmpDir)) {
    mkdir($uploadTmpDir, 0755, true);
}

// Función para parsear XML (sin cambios, ya era robusta)
function parseCfdiXmlString($xmlString) {
    //... (Tu función parseCfdiXmlString aquí, sin cambios)
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if (!$xml) return false;

    $getByLocal = function($node, $local) {
        $res = $node->xpath("//*[local-name()='$local']");
        return ($res && count($res)>0)? $res : null;
    };

    $comprobante = $getByLocal($xml, 'Comprobante')?: $xml;
    $emisor      = $getByLocal($xml, 'Emisor');
    $receptor    = $getByLocal($xml, 'Receptor');
    $timbre      = $getByLocal($xml, 'TimbreFiscalDigital');

    if (!$comprobante ||!$emisor ||!$receptor ||!$timbre) {
        return false;
    }

    $data =;
    $data['uuid']               = (string) $timbre?: '';
    $data['fecha']              = (string) $comprobante['Fecha']?: null;
    $data['subtotal']           = (string) $comprobante?: '0.00';
    $data['total']              = (string) $comprobante?: '0.00';
    $data['emisor_rfc']         = (string) $emisor?: '';
    $data['receptor_rfc']       = (string) $receptor?: '';
    //... (resto de los campos que necesites)
    $data['version']            = (string) $comprobante['Version']?: '';
    $data['moneda']             = (string) $comprobante['Moneda']?: '';
    $data['metodo_pago']        = (string) $comprobante['MetodoPago']?: '';
    $data['forma_pago']         = (string) $comprobante['FormaPago']?: '';
    $data['lugar_expedicion']   = (string) $comprobante['LugarExpedicion']?: '';
    $data['no_certificado']     = (string) $comprobante['NoCertificado']?: '';
    $data['condiciones_pago']   = (string) $comprobante?: '';
    $data['exportacion']        = (string) $comprobante['Exportacion']?: '';
    $data['tipo_comprobante']   = (string) $comprobante?: '';
    $data['serie']              = (string) $comprobante?: '';
    $data['folio']              = (string) $comprobante['Folio']?: '';
    $data['emisor_nombre']      = (string) $emisor['Nombre']?: '';
    $data['receptor_nombre']    = (string) $receptor['Nombre']?: '';
    $data['receptor_uso_cfdi']  = (string) $receptor?: '';
    $data['receptor_domicilio'] = (string) $receptor?: '';
    $data['no_certificado_sat'] = (string) $timbre?: '';
    $data['rfc_prov_certif']    = (string) $timbre?: '';

    return $data;
}

$results = ['success' => true, 'parsed' =>, 'errors' =>];

if (empty($_FILES)) {
    $results['success'] = false;
    $results['message'] = "No se recibió ningún archivo.";
    ob_end_clean(); // Limpiar buffer antes de la salida
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400); // Bad Request
    echo json_encode($results);
    exit;
}

// Función para manejar un archivo XML temporal
$handleXmlTmp = function($tmpPath, $originalName) use (&$results, $uploadTmpDir) {
    //... (Tu función handleXmlTmp aquí, sin cambios)
    $contents = file_get_contents($tmpPath);
    $parsed = parseCfdiXmlString($contents);
    if (!$parsed) {
        $results['errors'] = "Archivo no es CFDI válido: $originalName";
        return;
    }
    $tmpFilename = uniqid('cfdi_'). '.xml';
    $destTmp = $uploadTmpDir. $tmpFilename;
    if (file_put_contents($destTmp, $contents) === false) {
        $results['errors'] = "No se pudo guardar temporal: $originalName";
        return;
    }
    $parsed['_tmp_file'] = $tmpFilename;
    $results['parsed'] = $parsed;
};

// Procesamiento de archivos (sin cambios, ya era robusto)
foreach ($_FILES as $inputName => $fileInfo) {
    if (is_array($fileInfo['name'])) {
        for ($i = 0; $i < count($fileInfo['name']); $i++) {
            if ($fileInfo['error'][$i]!== UPLOAD_ERR_OK) continue;
            $tmp = $fileInfo['tmp_name'][$i];
            $name = $fileInfo['name'][$i];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === 'xml') {
                $handleXmlTmp($tmp, $name);
            } elseif ($ext === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($tmp) === true) {
                    for ($j = 0; $j < $zip->numFiles; $j++) {
                        $entry = $zip->getNameIndex($j);
                        if (strtolower(pathinfo($entry, PATHINFO_EXTENSION))!== 'xml') continue;
                        $contents = $zip->getFromName($entry);
                        if ($contents === false) continue;
                        
                        $parsed = parseCfdiXmlString($contents);
                        if (!$parsed) {
                            $results['errors'] = "XML dentro de ZIP no válido: $entry";
                            continue;
                        }
                        $tmpFilename = uniqid('cfdi_'). '.xml';
                        $destTmp = $uploadTmpDir. $tmpFilename;
                        file_put_contents($destTmp, $contents);
                        $parsed['_tmp_file'] = $tmpFilename;
                        $results['parsed'] = $parsed;
                    }
                    $zip->close();
                } else {
                    $results['errors'] = "ZIP corrupto: $name";
                }
            }
        }
    }
}

// Limpiar cualquier salida accidental y enviar la respuesta JSON final
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($results, JSON_UNESCAPED_UNICODE);
exit;