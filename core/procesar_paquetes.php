<?php
// core/procesar_paquetes.php
declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/class/db.php";

use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\Exceptions\OpenZipFileException;
use CfdiUtils\Nodes\XmlNodeUtils;
use PhpCfdi\CfdiToPdf\Converter;
use PhpCfdi\CfdiToPdf\Builders\Html2PdfBuilder;
use PhpCfdi\CfdiToPdf\CfdiDataBuilder;
use Throwable;
use PDO;
use SimpleXMLElement;

header('Content-Type: application/json; charset=utf-8');

/**
 * Extrae los 27 campos requeridos por la tabla `facturas` utilizando SimpleXML.
 */
function parseCfdiXmlString(string $xmlString): array|bool
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if (!$xml) return false;

    // Helper para buscar nodos por local-name
    $getByLocal = function (SimpleXMLElement $node, string $local): ?SimpleXMLElement {
        $res = $node->xpath("//*[local-name()='$local']");
        return ($res && count($res) > 0) ? $res[0] : null;
    };

    $comprobante = $getByLocal($xml, 'Comprobante') ?: $xml;
    $emisor      = $getByLocal($xml, 'Emisor');
    $receptor    = $getByLocal($xml, 'Receptor');
    $timbre      = $getByLocal($xml, 'TimbreFiscalDigital');

    if (!$comprobante || !$emisor || !$receptor || !$timbre) {
        return false;
    }

    // --- Extracción de Régimen Fiscal del Emisor ---
    $emisorRegimenValue = (string) $emisor['RegimenFiscal'] ?: '';
    if (empty($emisorRegimenValue)) {
        // Fallback: buscar el sub-nodo RegimenFiscal (CFDI 3.3)
        $regimenes = $comprobante->xpath('//*[local-name()="Emisor"]/*[local-name()="RegimenFiscal"]');
        if (!empty($regimenes)) {
            $emisorRegimenValue = (string) $regimenes[0]['Regimen'];
        }
    }

    // --- Extracción de Régimen Fiscal del Receptor ---
    $receptorRegimenValue = (string) $receptor['RegimenFiscalReceptor'] ?: '';
    
    // Extracción de sub-nodos (solo en CFDI 3.3/4.0)
    $regimenReceptor = $comprobante->xpath('//*[local-name()="Receptor"]/*[local-name()="RegimenFiscal"]');
    if (!empty($regimenReceptor)) {
        $receptorRegimenValue = (string) $regimenReceptor[0]['Regimen'];
    }


    $data = [];
    $data['uuid']    = (string) $timbre['UUID'] ?: '';
    $data['version'] = (string) $comprobante['Version'] ?: (string) $comprobante['version'] ?: '';
    $data['fecha'] = (string) $comprobante['Fecha'] ?: (string) $comprobante['fecha'] ?: null;
    $data['subtotal'] = (string) $comprobante['SubTotal'] ?: (string) $comprobante['subTotal'] ?: '0.00';
    $data['total'] = (string) $comprobante['Total'] ?: (string) $comprobante['total'] ?: '0.00';
    $data['moneda'] = (string) $comprobante['Moneda'] ?: '';
    $data['metodo_pago'] = (string) $comprobante['MetodoPago'] ?: (string) $comprobante['metodoDePago'] ?: '';
    $data['forma_pago']  = (string) $comprobante['FormaPago'] ?: (string) $comprobante['formaPago'] ?: '';
    $data['lugar_expedicion'] = (string) $comprobante['LugarExpedicion'] ?: '';
    $data['no_certificado'] = (string) $comprobante['NoCertificado'] ?: '';
    $data['condiciones_pago'] = (string) $comprobante['CondicionesDePago'] ?: '';
    $data['exportacion'] = (string) $comprobante['Exportacion'] ?: '';
    $data['tipo_comprobante'] = (string) $comprobante['TipoDeComprobante'] ?: (string) $comprobante['tipoDeComprobante'] ?: '';
    $data['emisor_rfc'] = (string) $emisor['Rfc'] ?: (string) $emisor['RFC'] ?: '';
    $data['emisor_nombre'] = (string) $emisor['Nombre'] ?: '';
    $data['emisor_regimen'] = $emisorRegimenValue;
    $data['receptor_rfc'] = (string) $receptor['Rfc'] ?: (string) $receptor['RFC'] ?: '';
    $data['receptor_nombre'] = (string) $receptor['Nombre'] ?: '';
    $data['receptor_domicilio'] = (string) $receptor['DomicilioFiscalReceptor'] ?: '';
    $data['receptor_regimen'] = $receptorRegimenValue;
    $data['receptor_uso_cfdi'] = (string) $receptor['UsoCFDI'] ?: (string) $receptor['Uso'] ?: '';
    $data['no_certificado_sat'] = (string) $timbre['NoCertificadoSAT'] ?: '';
    $data['rfc_prov_certif'] = (string) $timbre['RfcProvCertif'] ?: '';
    $data['serie'] = (string) $comprobante['Serie'] ?: '';
    $data['folio'] = (string) $comprobante['Folio'] ?: '';

    return array_map('strval', $data);
}


// convertir XML a PDF
function convertirXmlAPdf(string $contenidoXml, string $rutaPdfDestino): bool
{
    try {
        $builder = new Html2PdfBuilder();
        $converter = new Converter($builder);
        $comprobante = XmlNodeUtils::nodeFromXmlString($contenidoXml);
        $cfdiData = (new CfdiDataBuilder())->build($comprobante);
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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de solicitud no proporcionado.']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a BD: ' . $e->getMessage()]);
    exit;
}

try {
    // Obtener la solicitud y paquetes
    $stmt = $db->prepare("SELECT paquetes_json FROM cf_solicitudes WHERE id_solicitud = ? AND estado = 'terminada'");
    $stmt->execute([$idSolicitud]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$solicitud || empty($solicitud['paquetes_json'])) {
        throw new Exception("La solicitud no está terminada o no contiene paquetes válidos.");
    }
    $paquetes = json_decode($solicitud['paquetes_json'], true);
    if (!is_array($paquetes) || empty($paquetes)) {
        throw new Exception("No hay paquetes para procesar.");
    }

    $uploadXmlDir = __DIR__ . "/../uploads/xml/";
    $uploadPdfDir = __DIR__ . "/../uploads/pdf/";
    @mkdir($uploadXmlDir, 0755, true);
    @mkdir($uploadPdfDir, 0755, true);

    $insertedCount = 0;
    $errors = [];
    $duplicatedCount = 0;
    $hasPendingPaquetes = false;

    // Procesar cada paquete descargado
    foreach ($paquetes as &$paquete) {
        if (empty($paquete['zip_path']) || ($paquete['estado'] ?? '') !== 'descargado') {
            if (($paquete['estado'] ?? '') === 'pendiente') {
                $hasPendingPaquetes = true;
            }
            continue;
        }

        if (($paquete['procesado'] ?? 0) === 1) {
            continue;
        }
        
        $projectRoot = realpath(dirname(__DIR__));
        $relativeZipPath = ltrim($paquete['zip_path'], '/\\');
        $zipfile = $projectRoot . DIRECTORY_SEPARATOR . $relativeZipPath;
        
        if (!file_exists($zipfile)) {
            $errors[] = "Archivo ZIP no encontrado: {$paquete['zip_path']}";
            $paquete['procesado'] = 2;
            continue;
        }

        try {
            // Leer el paquete
            $reader = CfdiPackageReader::createFromFile($zipfile);
            $cfdis = iterator_to_array($reader->cfdis());

            if (count($cfdis) === 0) {
                $errors[] = "El paquete {$paquete['zip_path']} no contiene CFDIs.";
                $paquete['procesado'] = 2;
                continue;
            }

            foreach ($cfdis as $uuid => $content) {
                $uuidStr = (string)$uuid;

                // Verificar si hay duplicado
                $check = $db->prepare("SELECT uuid FROM facturas WHERE uuid = ? LIMIT 1");
                $check->execute([$uuidStr]);
                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $duplicatedCount++;
                    continue;
                }

                $xmlFile = $uuidStr . '.xml';
                $pdfFile = $uuidStr . '.pdf';
                $xmlPath = $uploadXmlDir . $xmlFile;
                $pdfPath = $uploadPdfDir . $pdfFile;

                // Guardar XML
                if (file_put_contents($xmlPath, $content) === false) {
                    $errors[] = "No se pudo guardar XML del UUID: {$uuidStr}";
                    continue;
                }

                // Parsear XML para campos
                $data = parseCfdiXmlString($content);
                if (!$data) {
                    $errors[] = "No se pudo parsear XML del UUID: {$uuidStr}.";
                    @unlink($xmlPath);
                    continue;
                }
                
                // Convertir a PDF
                if (!convertirXmlAPdf($content, $pdfPath)) {
                    $errors[] = "No se pudo generar PDF del UUID: {$uuidStr}.";
                    $pdfFile = null;
                }

                // Insertar en BD
                $insert = $db->prepare(
                    "INSERT INTO facturas
                    (uuid, version, fecha, subtotal, total, moneda, metodo_pago, forma_pago, lugar_expedicion, no_certificado, condiciones_pago, exportacion, tipo_comprobante, emisor_rfc, emisor_nombre, emisor_regimen, receptor_rfc, receptor_nombre, receptor_domicilio, receptor_regimen, receptor_uso_cfdi, no_certificado_sat, rfc_prov_certif, xml_file, pdf_file, serie, folio)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $insert->execute([
                    $data['uuid'], $data['version'], $data['fecha'], $data['subtotal'], $data['total'],
                    $data['moneda'], $data['metodo_pago'], $data['forma_pago'], $data['lugar_expedicion'],
                    $data['no_certificado'], 
                    $data['condiciones_pago'], $data['exportacion'], $data['tipo_comprobante'],
                    $data['emisor_rfc'], $data['emisor_nombre'], $data['emisor_regimen'], 
                    $data['receptor_rfc'], $data['receptor_nombre'], $data['receptor_domicilio'],
                    $data['receptor_regimen'],
                    $data['receptor_uso_cfdi'], $data['no_certificado_sat'], $data['rfc_prov_certif'], 
                    $xmlFile, $pdfFile, $data['serie'], $data['folio']
                ]);

                if ($insert->rowCount() > 0) {
                    $insertedCount++;
                } else {
                    $errors[] = "Error al insertar UUID {$uuidStr}: no se insertó registro.";
                    @unlink($xmlPath);
                    @unlink($pdfPath);
                }
            }
            
            // Marcar paquete como procesado
            $paquete['procesado'] = 1;
            
        } catch (OpenZipFileException $e) {
            $errors[] = "No se pudo abrir ZIP '{$paquete['zip_path']}': " . $e->getMessage();
            $paquete['procesado'] = 2;
        } catch (Throwable $e) {
            $errors[] = "Error crítico procesando paquete '{$paquete['zip_path']}': " . $e->getMessage();
            $paquete['procesado'] = 2;
        }
    }
    
    // Actualizar el estado de la solicitud y el JSON de paquetes
    
    $nuevoEstadoSolicitud = 'terminada';
    
    if (!$hasPendingPaquetes && $insertedCount > 0 && empty($errors)) {
        $stmtDelete = $db->prepare("DELETE FROM cf_solicitudes WHERE id_solicitud = ?");
        $stmtDelete->execute([$idSolicitud]);
        $message = "Proceso completado. Solicitud eliminada. Se registraron {$insertedCount} nuevas facturas.";
    } else {
        // Actualizar el JSON de paquetes en la solicitud
        $stmtUpdate = $db->prepare("UPDATE cf_solicitudes SET paquetes_json = ?, estado = ?, ultima_verificacion = NOW() WHERE id_solicitud = ?");
        $stmtUpdate->execute([json_encode(array_values($paquetes), JSON_UNESCAPED_UNICODE), $nuevoEstadoSolicitud, $idSolicitud]);
        $message = "Proceso completado. Se registraron {$insertedCount} nuevas facturas.";
    }
    
    if ($duplicatedCount > 0) {
        $message .= " Se omitieron {$duplicatedCount} duplicados.";
    }
    if (!empty($errors)) {
        $message .= " Se encontraron " . count($errors) . " errores de procesamiento. Revise el log.";
    }

    echo json_encode(['success' => true, 'message' => $message, 'inserted' => $insertedCount, 'errors' => $errors]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error crítico: ' . $e->getMessage()]);
}
