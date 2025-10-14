<?php
// core/procesar_paquetes.php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../config.php"; // Este archivo ya crea la variable global $conn
require_once __DIR__ . "/class/db.php";

// Usamos las librerías que nos ayudarán en el proceso
use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\Exceptions\OpenZipFileException;
use CfdiUtils\Nodes\XmlNodeUtils;
use PhpCfdi\CfdiToPdf\Converter;
use PhpCfdi\CfdiToPdf\Builders\Html2PdfBuilder;
use PhpCfdi\CfdiToPdf\CfdiDataBuilder;

header('Content-Type: application/json; charset=utf-8');

function parseCfdiXmlString($xmlString)
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if (!$xml) {
        return false;
    }

    $getByLocal = function ($node, $local) {
        $res = $node->xpath("//*[local-name()='$local']");
        return ($res && count($res) > 0) ? $res[0] : null;
    };

    $comprobante = $getByLocal($xml, 'Comprobante') ?: $xml;
    $emisor = $getByLocal($xml, 'Emisor');
    $receptor = $getByLocal($xml, 'Receptor');
    $timbre = $getByLocal($xml, 'TimbreFiscalDigital');

    if (!$comprobante || !$emisor || !$receptor || !$timbre) {
        return false;
    }

    $data = [];
    $data['uuid'] = (string) $timbre['UUID'] ?: '';
    $data['version'] = (string) $comprobante['Version'] ?: '';
    $data['fecha'] = (string) $comprobante['Fecha'] ?: null;
    $data['subtotal'] = (string) $comprobante['SubTotal'] ?: '0.00';
    $data['total'] = (string) $comprobante['Total'] ?: '0.00';
    $data['moneda'] = (string) $comprobante['Moneda'] ?: '';
    $data['metodo_pago'] = (string) $comprobante['MetodoPago'] ?: '';
    $data['forma_pago'] = (string) $comprobante['FormaPago'] ?: '';
    $data['lugar_expedicion'] = (string) $comprobante['LugarExpedicion'] ?: '';
    $data['no_certificado'] = (string) $comprobante['NoCertificado'] ?: '';
    $data['tipo_comprobante'] = (string) $comprobante['TipoDeComprobante'] ?: '';
    $data['serie'] = (string) $comprobante['Serie'] ?: '';
    $data['folio'] = (string) $comprobante['Folio'] ?: '';
    $data['emisor_rfc'] = (string) $emisor['Rfc'] ?: '';
    $data['emisor_nombre'] = (string) $emisor['Nombre'] ?: '';
    $data['receptor_rfc'] = (string) $receptor['Rfc'] ?: '';
    $data['receptor_nombre'] = (string) $receptor['Nombre'] ?: '';
    $data['receptor_uso_cfdi'] = (string) $receptor['UsoCFDI'] ?: '';

    return $data;
}

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


$input = json_decode(file_get_contents('php://input'), true);
$idSolicitud = $input['id_solicitud'] ?? null;

if (!$idSolicitud) {
    echo json_encode(['success' => false, 'message' => 'ID de solicitud no proporcionado.']);
    exit;
}

try {
    $db = (new Database())->getConnection();

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("La conexión a la base de datos (MySQLi) falló: " . ($conn->connect_error ?? 'Error desconocido en config.php'));
    }

    // 1. Obtener los paquetes de la solicitud
    $stmt = $db->prepare("SELECT paquetes_json FROM cf_solicitudes WHERE id_solicitud = ? AND estado = 'terminada'");
    $stmt->execute([$idSolicitud]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud || empty($solicitud['paquetes_json'])) {
        throw new Exception("La solicitud no está terminada o no tiene paquetes para procesar.");
    }

    $paquetes = json_decode($solicitud['paquetes_json'], true);
    if (!is_array($paquetes)) {
        throw new Exception("El formato del listado de paquetes no es válido.");
    }

    $uploadXmlDir = __DIR__ . "/../uploads/xml/";
    $uploadPdfDir = __DIR__ . "/../uploads/pdf/";
    if (!is_dir($uploadXmlDir)) {
        mkdir($uploadXmlDir, 0755, true);
    }
    if (!is_dir($uploadPdfDir)) {
        mkdir($uploadPdfDir, 0755, true);
    }

    $insertedCount = 0;
    $errors = [];
    $duplicatedCount = 0;

    // 2. Procesar cada paquete (archivo ZIP)
    foreach ($paquetes as $paquete) {
        if (empty($paquete['zip_path'])) {
            continue;
        }

        $projectRoot = dirname(__DIR__);
        $zipfile = $projectRoot . DIRECTORY_SEPARATOR . ltrim($paquete['zip_path'], '/\\');
    

        if (!file_exists($zipfile)) {
            $errors[] = "No se encontró el archivo ZIP: " . $paquete['zip_path'];
            continue;
        }

        try {
            $cfdiReader = CfdiPackageReader::createFromFile($zipfile);

            // 3. Leer cada CFDI dentro del ZIP
            foreach ($cfdiReader->cfdis() as $uuid => $content) {
                // Evitar duplicados por UUID
                $checkStmt = $db->prepare("SELECT uuid FROM facturas WHERE uuid = ?");
                $checkStmt->execute([$uuid]);
                if ($checkStmt->fetch()) {
                    $duplicatedCount++;
                    continue;
                }

                $finalXmlName = $uuid . '.xml';
                $destXml = $uploadXmlDir . $finalXmlName;

                if (file_put_contents($destXml, $content) === false) {
                    $errors[] = "No se pudo guardar el XML para el UUID: {$uuid}";
                    continue;
                }

                $finalPdfName = $uuid . '.pdf';
                $destPdf = $uploadPdfDir . $finalPdfName;
                if (!convertirXmlAPdf($content, $destPdf)) {
                    $errors[] = "Error al convertir a PDF el UUID: {$uuid}";
                    @unlink($destXml);
                    continue;
                }

                $data = parseCfdiXmlString($content);
                if (!$data) {
                    $errors[] = "No se pudo parsear el XML del UUID: {$uuid}";
                    @unlink($destXml);
                    @unlink($destPdf);
                    continue;
                }

                // Insertar en la base de datos usando la conexión $conn de config.php
                $stmtInsert = $conn->prepare("INSERT INTO facturas (uuid, version, fecha, subtotal, total, moneda, metodo_pago, forma_pago, lugar_expedicion, no_certificado, tipo_comprobante, emisor_rfc, emisor_nombre, receptor_rfc, receptor_nombre, receptor_uso_cfdi, xml_file, pdf_file, serie, folio) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmtInsert->bind_param(
                    "ssssssssssssssssssss",
                    $uuid,
                    $data['version'],
                    $data['fecha'],
                    $data['subtotal'],
                    $data['total'],
                    $data['moneda'],
                    $data['metodo_pago'],
                    $data['forma_pago'],
                    $data['lugar_expedicion'],
                    $data['no_certificado'],
                    $data['tipo_comprobante'],
                    $data['emisor_rfc'],
                    $data['emisor_nombre'],
                    $data['receptor_rfc'],
                    $data['receptor_nombre'],
                    $data['receptor_uso_cfdi'],
                    $finalXmlName,
                    $finalPdfName,
                    $data['serie'],
                    $data['folio']
                );

                if ($stmtInsert->execute()) {
                    $insertedCount++;
                } else {
                    $errors[] = "Error al guardar en BD el UUID {$uuid}: " . $stmtInsert->error;
                    @unlink($destXml);
                    @unlink($destPdf);
                }
                $stmtInsert->close();
            }
        } catch (OpenZipFileException $e) {
            $errors[] = "No se pudo abrir el archivo ZIP '{$paquete['zip_path']}': " . $e->getMessage();
        } catch (Throwable $e) {
            $errors[] = "Error procesando el paquete '{$paquete['zip_path']}': " . $e->getMessage();
        }
    }

    // 4. Si no hubo errores y se insertó al menos una factura, eliminar la solicitud
    if ($insertedCount > 0 && empty($errors)) {
        $stmtDelete = $db->prepare("DELETE FROM cf_solicitudes WHERE id_solicitud = ?");
        $stmtDelete->execute([$idSolicitud]);
    }

    $message = "Proceso completado. Se registraron {$insertedCount} nuevas facturas.";
    if ($duplicatedCount > 0) {
        $message .= " Se omitieron {$duplicatedCount} facturas que ya existían.";
    }
    if (!empty($errors)) {
        $message .= " Se encontraron los siguientes errores: " . implode("; ", $errors);
    }

    echo json_encode(['success' => true, 'message' => $message, 'inserted' => $insertedCount, 'errors' => $errors]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error Crítico: ' . $e->getMessage()]);
}
