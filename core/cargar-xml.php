<?php
// app-m/core/cargar-xml.
// Forzar JSON siempre
header('Content-Type: application/json; charset=utf-8');

// Capturar cualquier salida no deseada
ob_start();

require_once __DIR__ . "/../config.php"; // debe definir $conn (mysqli)
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
libxml_use_internal_errors(true);

// límites y paths
$maxFileSize = 10 * 1024 * 1024; // 10 MB por archivo (ajusta)
$uploadTmpDir = __DIR__ . "/../uploads/tmp/";
if (!is_dir($uploadTmpDir)) mkdir($uploadTmpDir, 0755, true);

// helper para parsear un XML string y devolver array de datos o false
function parseCfdiXmlString($xmlString) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if (!$xml) return false;

    // Podemos buscar por local-name para no depender del prefijo
    $names = [];
    foreach ($xml->getDocNamespaces(true) as $k => $v) {
        $names[$k] = $v;
    }

    // función auxiliar para buscar nodos por local-name
    $getByLocal = function($node, $local) {
        $res = $node->xpath("//*[local-name()='$local']");
        return ($res && count($res)>0) ? $res[0] : null;
    };

    $comprobante = $getByLocal($xml, 'Comprobante') ?: $xml;
    $emisor      = $getByLocal($xml, 'Emisor');
    $receptor    = $getByLocal($xml, 'Receptor');
    // timbre normalmente está en complemento -> TimbreFiscalDigital
    $timbre      = $getByLocal($xml, 'TimbreFiscalDigital');

    if (!$comprobante || !$emisor || !$receptor || !$timbre) {
        // devolver false si faltan nodos esenciales
        return false;
    }

    // atributos (ver nombres comunes, uso string cast)
    $data = [];
    $data['uuid']               = (string) $timbre['UUID'] ?: '';
    $data['version']            = (string) $comprobante['Version'] ?: (string) $comprobante['version'] ?: '';
    $data['fecha']              = (string) $comprobante['Fecha'] ?: (string) $comprobante['fecha'] ?: null;
    $data['subtotal']           = (string) $comprobante['SubTotal'] ?: (string) $comprobante['subTotal'] ?: '0.00';
    $data['total']              = (string) $comprobante['Total'] ?: (string) $comprobante['total'] ?: '0.00';
    $data['moneda']             = (string) $comprobante['Moneda'] ?: '';
    $data['metodo_pago']        = (string) $comprobante['MetodoPago'] ?: (string) $comprobante['metodoDePago'] ?: '';
    $data['forma_pago']         = (string) $comprobante['FormaPago'] ?: (string) $comprobante['formaPago'] ?: '';
    $data['lugar_expedicion']   = (string) $comprobante['LugarExpedicion'] ?: '';
    $data['no_certificado']     = (string) $comprobante['NoCertificado'] ?: '';
    $data['condiciones_pago']   = (string) $comprobante['CondicionesDePago'] ?: '';
    $data['exportacion']        = (string) $comprobante['Exportacion'] ?: '';
    $data['tipo_comprobante']   = (string) $comprobante['TipoDeComprobante'] ?: (string) $comprobante['tipoDeComprobante'] ?: '';
    $data['serie']              = (string) $comprobante['Serie'] ?: '';
    $data['folio']              = (string) $comprobante['Folio'] ?: '';

    // emisor
    $data['emisor_rfc']         = (string) $emisor['Rfc'] ?: (string) $emisor['RFC'] ?: '';
    $data['emisor_nombre']      = (string) $emisor['Nombre'] ?: '';

    // receptor
    $data['receptor_rfc']       = (string) $receptor['Rfc'] ?: (string) $receptor['RFC'] ?: '';
    $data['receptor_nombre']    = (string) $receptor['Nombre'] ?: '';
    $data['receptor_uso_cfdi']  = (string) $receptor['UsoCFDI'] ?: (string) $receptor['Uso'] ?: '';
    $data['receptor_domicilio'] = (string) $receptor['DomicilioFiscalReceptor'] ?: '';

    // timbre adicional
    $data['no_certificado_sat'] = (string) $timbre['NoCertificadoSAT'] ?: '';
    $data['rfc_prov_certif']    = (string) $timbre['RfcProvCertif'] ?: '';

    return $data;
}

// recibe archivos (xml o zip) - soporta múltiples via 'xmlFile' o 'zipFile'
$results = ['success' => true, 'parsed' => [], 'errors' => []];

// function to handle single uploaded xml tmp file
$handleXmlTmp = function($tmpPath, $originalName) use (&$results, $uploadTmpDir) {
    $contents = file_get_contents($tmpPath);
    $parsed = parseCfdiXmlString($contents);
    if (!$parsed) {
        $results['errors'][] = "Archivo no es CFDI válido: $originalName";
        return;
    }
    // generar nombre temporal único y guardar
    $tmpFilename = uniqid('cfdi_') . '.xml';
    $destTmp = $uploadTmpDir . $tmpFilename;
    if (file_put_contents($destTmp, $contents) === false) {
        $results['errors'][] = "No se pudo guardar temporal: $originalName";
        return;
    }
    // añadir info para el cliente (incluye ruta temporal para posterior guardado)
    $parsed['_tmp_file'] = $tmpFilename;
    $results['parsed'][] = $parsed;
};

// revisión de archivos subidos por input xmlFile o zipFile
if (!empty($_FILES)) {
    foreach ($_FILES as $inputName => $fileInfo) {
        // Soporta múltiples archivos por input (array)
        if (is_array($fileInfo['name'])) {
            $count = count($fileInfo['name']);
            for ($i=0;$i<$count;$i++) {
                if ($fileInfo['error'][$i] !== UPLOAD_ERR_OK) {
                    $results['errors'][] = "Error subiendo archivo: ".$fileInfo['name'][$i];
                    continue;
                }
                $tmp = $fileInfo['tmp_name'][$i];
                $name = $fileInfo['name'][$i];
                // tipo
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($ext === 'xml') {
                    // procesar xml
                    $handleXmlTmp($tmp, $name);
                } elseif ($ext === 'zip') {
                    // extraer zip y buscar xmls
                    $zip = new ZipArchive();
                    if ($zip->open($tmp) === true) {
                        for ($j=0; $j < $zip->numFiles; $j++) {
                            $entry = $zip->getNameIndex($j);
                            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'xml') continue;
                            $stream = $zip->getStream($entry);
                            if (!$stream) {
                                $results['errors'][] = "No se pudo leer el archivo dentro del zip: $entry";
                                continue;
                            }
                            $contents = stream_get_contents($stream);
                            fclose($stream);
                            // parsear cada xml content
                            $parsed = parseCfdiXmlString($contents);
                            if (!$parsed) {
                                $results['errors'][] = "XML dentro de ZIP no válido: $entry";
                                continue;
                            }
                            $tmpFilename = uniqid('cfdi_') . '.xml';
                            $destTmp = $uploadTmpDir . $tmpFilename;
                            if (file_put_contents($destTmp, $contents) === false) {
                                $results['errors'][] = "No se pudo guardar temporal xml de zip: $entry";
                                continue;
                            }
                            $parsed['_tmp_file'] = $tmpFilename;
                            $results['parsed'][] = $parsed;
                        }
                        $zip->close();
                    } else {
                        $results['errors'][] = "ZIP corrupto o no se pudo abrir: $name";
                    }
                } else {
                    $results['errors'][] = "Tipo no soportado: $name";
                }
            }
        } else {
            // caso simple (no array)
            if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
                $results['errors'][] = "Error subiendo archivo: ".$fileInfo['name'];
                continue;
            }
            $tmp = $fileInfo['tmp_name'];
            $name = $fileInfo['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === 'xml') {
                $handleXmlTmp($tmp, $name);
            } elseif ($ext === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($tmp) === true) {
                    for ($j=0; $j < $zip->numFiles; $j++) {
                        $entry = $zip->getNameIndex($j);
                        if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'xml') continue;
                        $stream = $zip->getStream($entry);
                        if (!$stream) {
                            $results['errors'][] = "No se pudo leer el archivo dentro del zip: $entry";
                            continue;
                        }
                        $contents = stream_get_contents($stream);
                        fclose($stream);
                        $parsed = parseCfdiXmlString($contents);
                        if (!$parsed) {
                            $results['errors'][] = "XML dentro de ZIP no válido: $entry";
                            continue;
                        }
                        $tmpFilename = uniqid('cfdi_') . '.xml';
                        $destTmp = $uploadTmpDir . $tmpFilename;
                        if (file_put_contents($destTmp, $contents) === false) {
                            $results['errors'][] = "No se pudo guardar temporal xml de zip: $entry";
                            continue;
                        }
                        $parsed['_tmp_file'] = $tmpFilename;
                        $results['parsed'][] = $parsed;
                    }
                    $zip->close();
                } else {
                    $results['errors'][] = "ZIP corrupto o no se pudo abrir: $name";
                }
            } else {
                $results['errors'][] = "Tipo no soportado: $name";
            }
        }
    }
} else {
    ob_end_clean();
    echo json_encode(["success" => false, "message" => "No se recibió ningún archivo"]);
    exit;
}

ob_end_clean(); // limpiar posibles warnings/HTML
try {
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error generando respuesta JSON",
        "error"   => $e->getMessage()
    ]);
}
exit;
