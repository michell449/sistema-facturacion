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

header('Content-Type: application/json; charset=utf-8');

// --- Helper: parsear CFDI XML ---
function parseCfdiXmlString(string $xmlString)
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if (!$xml) {
        return false;
    }
    $getByLocal = function ($node, $local) {
        $res = $node->xpath("//*[local-name()='{$local}']");
        return ($res && count($res)) ? $res[0] : null;
    };
    $comprobante = $getByLocal($xml, 'Comprobante') ?: $xml;
    $emisor = $getByLocal($xml, 'Emisor');
    $receptor = $getByLocal($xml, 'Receptor');
    $timbre = $getByLocal($xml, 'TimbreFiscalDigital');
    if (!$comprobante || !$emisor || !$receptor || !$timbre) {
        return false;
    }
    return [
        'uuid' => (string) $timbre['UUID'],
        'version' => (string) $comprobante['Version'] ?? (string) $comprobante['Version'],
        'fecha' => (string) $comprobante['Fecha'],
        'subtotal' => (string) ($comprobante['SubTotal'] ?? ''),
        'total' => (string) ($comprobante['Total'] ?? ''),
        'moneda' => (string) ($comprobante['Moneda'] ?? ''),
        'metodo_pago' => (string) ($comprobante['MetodoPago'] ?? ''),
        'forma_pago' => (string) ($comprobante['FormaPago'] ?? ''),
        'lugar_expedicion' => (string) ($comprobante['LugarExpedicion'] ?? ''),
        'no_certificado' => (string) ($comprobante['NoCertificado'] ?? ''),
        'tipo_comprobante' => (string) ($comprobante['TipoDeComprobante'] ?? $comprobante['TipoComprobante'] ?? ''),
        'serie' => (string) ($comprobante['Serie'] ?? ''),
        'folio' => (string) ($comprobante['Folio'] ?? ''),
        'emisor_rfc' => (string) $emisor['Rfc'],
        'emisor_nombre' => (string) $emisor['Nombre'],
        'receptor_rfc' => (string) $receptor['Rfc'],
        'receptor_nombre' => (string) $receptor['Nombre'],
        'receptor_uso_cfdi' => (string) ($receptor['UsoCFDI'] ?? $receptor['UsoCFDI'])
    ];
}

// --- Helper: convertir XML a PDF ---
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

// MAIN
$input = json_decode(file_get_contents('php://input'), true);
$idSolicitud = $input['id_solicitud'] ?? null;
if (!$idSolicitud) {
    echo json_encode(['success' => false, 'message' => 'ID de solicitud no proporcionado.']);
    exit;
}

try {
    $db = (new Database())->getConnection(); // asumo PDO
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a BD: ' . $e->getMessage()]);
    exit;
}

try {
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

    foreach ($paquetes as $paquete) {
        if (empty($paquete['zip_path'])) {
            $errors[] = "Paquete sin ruta ZIP definida.";
            continue;
        }
        $projectRoot = dirname(__DIR__);
        $relativeZipPath = ltrim($paquete['zip_path'], '/\\');
        $zipfile = realpath($projectRoot . DIRECTORY_SEPARATOR . $relativeZipPath);
        if (!$zipfile || !file_exists($zipfile)) {
            $errors[] = "Archivo ZIP no encontrado: {$paquete['zip_path']}";
            continue;
        }

        try {
            // Intentar leer el paquete con la librería (puede lanzar OpenZipFileException)
            // La librería acepta ruta de archivo
            $reader = CfdiPackageReader::createFromFile($zipfile);
            $cfdisIter = $reader->cfdis();
            $cfdis = iterator_to_array($cfdisIter);

            if (count($cfdis) === 0) {
                $errors[] = "El paquete {$paquete['zip_path']} no contiene CFDIs.";
                continue;
            }

            foreach ($cfdis as $uuid => $content) {
                // Normalizar UUID string
                $uuidStr = (string)$uuid;
                if (empty($uuidStr)) {
                    $errors[] = "CFDI sin UUID detectado en paquete {$paquete['zip_path']}.";
                    continue;
                }

                // Verificar duplicado (usando PDO)
                $check = $db->prepare("SELECT uuid FROM facturas WHERE uuid = ? LIMIT 1");
                $check->execute([$uuidStr]);
                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $duplicatedCount++;
                    continue;
                }

                // Guardar XML
                $xmlFile = $uuidStr . '.xml';
                $pdfFile = $uuidStr . '.pdf';
                $xmlPath = $uploadXmlDir . $xmlFile;
                $pdfPath = $uploadPdfDir . $pdfFile;

                if (file_put_contents($xmlPath, $content) === false) {
                    $errors[] = "No se pudo guardar XML del UUID: {$uuidStr}";
                    continue;
                }

                // Convertir a PDF
                if (!convertirXmlAPdf($content, $pdfPath)) {
                    $errors[] = "No se pudo generar PDF del UUID: {$uuidStr}";
                    @unlink($xmlPath);
                    continue;
                }

                // Parsear XML para campos
                $data = parseCfdiXmlString($content);
                if (!$data) {
                    $errors[] = "No se pudo parsear XML del UUID: {$uuidStr}";
                    @unlink($xmlPath);
                    @unlink($pdfPath);
                    continue;
                }

                // Insertar en BD (PDO) - ajustar campos según tu esquema real
                $insert = $db->prepare(
                    "INSERT INTO facturas 
                    (uuid, version, fecha, subtotal, total, moneda, metodo_pago, forma_pago, lugar_expedicion, no_certificado, tipo_comprobante, emisor_rfc, emisor_nombre, receptor_rfc, receptor_nombre, receptor_uso_cfdi, xml_file, pdf_file, serie, folio)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insert->execute([
                    $data['uuid'],
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
                    $xmlFile,
                    $pdfFile,
                    $data['serie'],
                    $data['folio']
                ]);

                if ($insert->rowCount() > 0) {
                    $insertedCount++;
                } else {
                    $errors[] = "Error al insertar UUID {$uuidStr}: no se insertó registro.";
                    @unlink($xmlPath);
                    @unlink($pdfPath);
                }
            }
        } catch (OpenZipFileException $e) {
            $errors[] = "No se pudo abrir ZIP '{$paquete['zip_path']}': " . $e->getMessage();
        } catch (Throwable $e) {
            $errors[] = "Error procesando paquete '{$paquete['zip_path']}': " . $e->getMessage();
        }
    }

    // Opcional: si todo se insertó correctamente, eliminar la solicitud (como en tu lógica)
    if ($insertedCount > 0 && empty($errors)) {
        $stmtDelete = $db->prepare("DELETE FROM cf_solicitudes WHERE id_solicitud = ?");
        $stmtDelete->execute([$idSolicitud]);
    }

    $message = "Proceso completado. Se registraron {$insertedCount} nuevas facturas.";
    if ($duplicatedCount > 0) {
        $message .= " Se omitieron {$duplicatedCount} duplicados.";
    }
    if (!empty($errors)) {
        $message .= " Errores: " . implode(" | ", $errors);
    }

    echo json_encode(['success' => true, 'message' => $message, 'inserted' => $insertedCount, 'errors' => $errors]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error crítico: ' . $e->getMessage()]);
}
