<?php
// core/procesar-paquetes.php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/class/db.php";

use CfdiUtils\Nodes\XmlNodeUtils;
use PhpCfdi\CfdiToPdf\Converter;
use PhpCfdi\CfdiToPdf\Builders\Html2PdfBuilder;
use PhpCfdi\CfdiToPdf\CfdiDataBuilder;

header('Content-Type: application/json; charset=utf-8');

// --- INICIO DE FUNCIONES REUTILIZADAS ---
// (Copiadas desde guardar-facturas.php y cargar-xml.php para mantener este script autocontenido)

/**
 * Convierte una cadena de texto XML de un CFDI a un archivo PDF.
 */
function convertirXmlAPdf($contenidoXml, $rutaPdfDestino)
{
    try {
        $builder = new Html2PdfBuilder();
        $converter = new Converter($builder);
        $comprobante = XmlNodeUtils::nodeFromXmlString($contenidoXml);
        $cfdiDataBuilder = new CfdiDataBuilder();
        $cfdiData = $cfdiDataBuilder->build($comprobante);

        $converter->createPdfAs($cfdiData, $rutaPdfDestino);
        return true;
    } catch (Throwable $e) {
        error_log("Error al convertir XML a PDF: " . $e->getMessage());
        return false;
    }
}

/**
 * Parsea una cadena de texto XML de un CFDI para extraer sus datos principales.
 */
function parseCfdiXmlString($xmlString)
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if (!$xml) return false;

    $getByLocal = function ($node, $local) {
        $res = $node->xpath("//*[local-name()='$local']");
        return ($res && count($res) > 0) ? $res[0] : null;
    };

    $comprobante = $getByLocal($xml, 'Comprobante') ?: $xml;
    $emisor      = $getByLocal($xml, 'Emisor');
    $receptor    = $getByLocal($xml, 'Receptor');
    $timbre      = $getByLocal($xml, 'TimbreFiscalDigital');

    if (!$comprobante || !$emisor || !$receptor || !$timbre) return false;

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
    $data['emisor_rfc']         = (string) $emisor['Rfc'] ?: (string) $emisor['RFC'] ?: '';
    $data['emisor_nombre']      = (string) $emisor['Nombre'] ?: '';
    $data['receptor_rfc']       = (string) $receptor['Rfc'] ?: (string) $receptor['RFC'] ?: '';
    $data['receptor_nombre']    = (string) $receptor['Nombre'] ?: '';
    $data['receptor_uso_cfdi']  = (string) $receptor['UsoCFDI'] ?: (string) $receptor['Uso'] ?: '';
    $data['receptor_domicilio'] = (string) $receptor['DomicilioFiscalReceptor'] ?: '';
    $data['no_certificado_sat'] = (string) $timbre['NoCertificadoSAT'] ?: '';
    $data['rfc_prov_certif']    = (string) $timbre['RfcProvCertif'] ?: '';

    return $data;
}
// --- FIN DE FUNCIONES REUTILIZADAS ---

$input = json_decode(file_get_contents('php://input'), true);
$idSolicitud = $input['id_solicitud'] ?? null;

if (!$idSolicitud) {
    echo json_encode(['success' => false, 'message' => 'ID de solicitud no proporcionado.']);
    exit;
}

try {
    $db = (new Database())->getConnection();

    // 1. Obtener la información de la solicitud
    $stmt = $db->prepare("SELECT paquetes_json FROM cf_solicitudes WHERE id_solicitud = ? AND estado = 'terminada'");
    $stmt->execute([$idSolicitud]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud || empty($solicitud['paquetes_json'])) {
        throw new Exception("No se encontró la solicitud, no está terminada o no contiene paquetes para procesar.");
    }

    $paquetes = json_decode($solicitud['paquetes_json'], true);

    $uploadXmlDir = __DIR__ . "/../uploads/xml/";
    $uploadPdfDir = __DIR__ . "/../uploads/pdf/";
    if (!is_dir($uploadXmlDir)) mkdir($uploadXmlDir, 0755, true);
    if (!is_dir($uploadPdfDir)) mkdir($uploadPdfDir, 0755, true);

    // 2. Preparar statement para insertar facturas
    $stmtInsert = $db->prepare("INSERT INTO facturas 
        (uuid, version, fecha, subtotal, total, moneda, metodo_pago, forma_pago, lugar_expedicion, no_certificado, condiciones_pago, exportacion, tipo_comprobante, emisor_rfc, emisor_nombre, receptor_rfc, receptor_nombre, receptor_uso_cfdi, no_certificado_sat, rfc_prov_certif, xml_file, pdf_file, serie, folio)
        VALUES (:uuid, :version, :fecha, :subtotal, :total, :moneda, :metodo_pago, :forma_pago, :lugar_expedicion, :no_certificado, :condiciones_pago, :exportacion, :tipo_comprobante, :emisor_rfc, :emisor_nombre, :receptor_rfc, :receptor_nombre, :receptor_uso_cfdi, :no_certificado_sat, :rfc_prov_certif, :xml_file, :pdf_file, :serie, :folio)");

    $insertedCount = 0;
    $errors = [];

    // 3. Procesar cada paquete ZIP
    foreach ($paquetes as $paquete) {
        if (empty($paquete['zip_path'])) continue;
        
        $zipPath = __DIR__ . '/../' . ltrim($paquete['zip_path'], '/\\');
        if (!file_exists($zipPath)) {
            $errors[] = "El archivo ZIP del paquete {$paquete['id']} no fue encontrado en {$zipPath}.";
            continue;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $errors[] = "No se pudo abrir el archivo ZIP: {$zipPath}";
            continue;
        }
        
        // 4. Procesar cada XML dentro del ZIP
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xml') continue;

            $xmlContent = $zip->getFromIndex($i);
            $data = parseCfdiXmlString($xmlContent);

            if (!$data || empty($data['uuid'])) {
                $errors[] = "Archivo XML no válido o sin UUID en el ZIP: {$filename}";
                continue;
            }

            // Evitar duplicados
            $stmtCheck = $db->prepare("SELECT uuid FROM facturas WHERE uuid = ?");
            $stmtCheck->execute([$data['uuid']]);
            if ($stmtCheck->fetch()) {
                continue; // Ya existe, lo saltamos
            }
            
            // Guardar XML
            $finalXmlName = $data['uuid'] . '.xml';
            $destXml = $uploadXmlDir . $finalXmlName;
            if (file_put_contents($destXml, $xmlContent) === false) {
                $errors[] = "No se pudo guardar el archivo XML: {$finalXmlName}";
                continue;
            }

            // Generar PDF
            $finalPdfName = $data['uuid'] . '.pdf';
            $destPdf = $uploadPdfDir . $finalPdfName;
            if (!convertirXmlAPdf($xmlContent, $destPdf)) {
                $errors[] = "Error al convertir a PDF el UUID: {$data['uuid']}";
                @unlink($destXml); // Limpiar XML si el PDF falla
                continue;
            }

            // Insertar en la base de datos
            $params = [
                ':uuid' => $data['uuid'], ':version' => $data['version'] ?? '', ':fecha' => $data['fecha'] ?? null, 
                ':subtotal' => $data['subtotal'] ?? '0.00', ':total' => $data['total'] ?? '0.00', ':moneda' => $data['moneda'] ?? '',
                ':metodo_pago' => $data['metodo_pago'] ?? '', ':forma_pago' => $data['forma_pago'] ?? '', ':lugar_expedicion' => $data['lugar_expedicion'] ?? '',
                ':no_certificado' => $data['no_certificado'] ?? '', ':condiciones_pago' => $data['condiciones_pago'] ?? '', ':exportacion' => $data['exportacion'] ?? '',
                ':tipo_comprobante' => $data['tipo_comprobante'] ?? '', ':emisor_rfc' => $data['emisor_rfc'] ?? '', ':emisor_nombre' => $data['emisor_nombre'] ?? '',
                ':receptor_rfc' => $data['receptor_rfc'] ?? '', ':receptor_nombre' => $data['receptor_nombre'] ?? '', ':receptor_uso_cfdi' => $data['receptor_uso_cfdi'] ?? '',
                ':no_certificado_sat' => $data['no_certificado_sat'] ?? '', ':rfc_prov_certif' => $data['rfc_prov_certif'] ?? '',
                ':xml_file' => $finalXmlName, ':pdf_file' => $finalPdfName, ':serie' => $data['serie'] ?? '', ':folio' => $data['folio'] ?? ''
            ];
            
            if ($stmtInsert->execute($params)) {
                $insertedCount++;
            } else {
                $errors[] = "Error al insertar en BD el UUID {$data['uuid']}: " . implode(", ", $stmtInsert->errorInfo());
                @unlink($destXml);
                @unlink($destPdf);
            }
        }
        $zip->close();
    }

    // 5. Eliminar la solicitud si no hubo errores críticos
    if (empty($errors) || $insertedCount > 0) {
        $stmtDelete = $db->prepare("DELETE FROM cf_solicitudes WHERE id_solicitud = ?");
        $stmtDelete->execute([$idSolicitud]);
    }
    
    $message = "Proceso completado. Se registraron {$insertedCount} nuevas facturas.";
    if (!empty($errors)) {
        $message .= " Se encontraron errores: " . implode("; ", $errors);
    }

    echo json_encode(['success' => true, 'message' => $message, 'inserted' => $insertedCount, 'errors' => $errors]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error Crítico: ' . $e->getMessage()]);
}

exit;