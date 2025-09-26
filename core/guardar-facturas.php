<?php
// app-m/core/guardar-facturas.php

// NUEVO: Cargar el autoloader de Composer para usar las librerías
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . "/../config.php"; // $conn (mysqli)
header('Content-Type: application/json; charset=utf-8');

use Mpdf\Mpdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * NUEVO: Función para generar el PDF de la factura.
 * Recibe los datos de la factura y la ruta donde guardar el PDF.
 * @param array $datosFactura Datos parseados del XML.
 * @param string $rutaGuardado Path completo donde se guardará el PDF.
 * @param string $contenidoXml Contenido del archivo XML para los sellos.
 * @return bool True si se creó, false en caso de error.
 */
function generarPdfFactura($datosFactura, $rutaGuardado, $contenidoXml)
{
    try {
        // --- INICIO: Lógica para el QR del SAT ---
        // El QR debe contener: RFC Emisor, RFC Receptor, Total y los últimos 8 dígitos del Sello Digital.
        // Extraemos el sello del comprobante.
        $sello = '';
        if (preg_match('/Sello="([^"]+)"/', $contenidoXml, $matches)) {
            $sello = $matches[1];
        }
        $ultimos8Sello = substr($sello, -8);

        $qrData = sprintf(
            "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?id=%s&re=%s&rr=%s&tt=%s&fe=%s",
            $datosFactura['uuid'],
            htmlspecialchars($datosFactura['emisor_rfc']), // Usar htmlspecialchars para seguridad
            htmlspecialchars($datosFactura['receptor_rfc']),
            $datosFactura['total'],
            $ultimos8Sello
        );

        $qrCode = (new QRCode)->render($qrData);
        // --- FIN: Lógica para el QR del SAT ---

        // --- INICIO: Extracción de conceptos (Mejora importante) ---
        // Tu parser actual no extrae los conceptos, los agregamos aquí.
        $xml = simplexml_load_string($contenidoXml);
        $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $conceptosHtml = '';
        $conceptos = $xml->xpath('//cfdi:Conceptos/cfdi:Concepto');
        foreach ($conceptos as $concepto) {
            $conceptosHtml .= '<tr>
                <td>' . htmlspecialchars((string)$concepto['ClaveProdServ']) . '</td>
                <td>' . htmlspecialchars((string)$concepto['Cantidad']) . '</td>
                <td>' . htmlspecialchars((string)$concepto['ClaveUnidad']) . '</td>
                <td>' . htmlspecialchars((string)$concepto['Descripcion']) . '</td>
                <td class="text-right">$' . number_format((float)$concepto['ValorUnitario'], 2) . '</td>
                <td class="text-right">$' . number_format((float)$concepto['Importe'], 2) . '</td>
            </tr>';
        }
        // --- FIN: Extracción de conceptos ---
        ob_start();
        $datos = $datosFactura; // Si necesitas renombrar
        include __DIR__ . '/../pages/plantilla-cfdi.inc.php'; // La plantilla debe usar las variables ya definidas
        $html = ob_get_clean();

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($rutaGuardado, \Mpdf\Output\Destination::FILE);

        return true;
    } catch (\Exception $e) {
        // En un entorno de producción, registrarías este error en un log.
        // error_log('Error al generar PDF: ' . $e->getMessage());
        return false;
    }
}


$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'No hay facturas para guardar']);
    exit;
}

$uploadTmpDir = __DIR__ . "/../uploads/tmp/";
$uploadXmlDir = __DIR__ . "/../uploads/xml/";
$uploadPdfDir = __DIR__ . "/../uploads/pdf/";
if (!is_dir($uploadXmlDir)) mkdir($uploadXmlDir, 0755, true);
if (!is_dir($uploadPdfDir)) mkdir($uploadPdfDir, 0755, true);

$items = $input['items'];
$inserted = [];
$errors = [];

// MODIFICADO: Se añade pdf_file a la consulta
$stmt = $conn->prepare("INSERT INTO facturas 
    (uuid, version, fecha, subtotal, total, moneda, metodo_pago, forma_pago, lugar_expedicion, no_certificado, condiciones_pago, exportacion, tipo_comprobante, emisor_rfc, emisor_nombre, receptor_rfc, receptor_nombre, receptor_domicilio, receptor_uso_cfdi, no_certificado_sat, rfc_prov_certif, xml_file, pdf_file, serie, folio)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error al preparar consulta: ' . $conn->error]);
    exit;
}

foreach ($items as $it) {
    if (empty($it['_tmp_file'])) {
        $errors[] = ['uuid' => $it['uuid'] ?? null, 'error' => 'Temp file faltante'];
        continue;
    }

    $uuid = $conn->real_escape_string($it['uuid']);
    $q = $conn->query("SELECT uuid FROM facturas WHERE uuid = '$uuid' LIMIT 1");
    if ($q && $q->num_rows > 0) {
        $errors[] = ['uuid' => $uuid, 'error' => 'UUID ya existe en la base'];
        @unlink($uploadTmpDir . $it['_tmp_file']);
        continue;
    }

    $tmpXmlPath = $uploadTmpDir . $it['_tmp_file'];
    $finalXmlName = $uuid . '.xml';
    $destXmlPath = $uploadXmlDir . $finalXmlName;
    if (!file_exists($tmpXmlPath) || !rename($tmpXmlPath, $destXmlPath)) {
        $errors[] = ['uuid' => $uuid, 'error' => 'No se pudo mover xml a carpeta final'];
        continue;
    }

    // --- NUEVO: Generación del PDF ---
    $finalPdfName = $uuid . '.pdf';
    $destPdfPath = $uploadPdfDir . $finalPdfName;
    $xmlContent = file_get_contents($destXmlPath); // Leemos el XML ya guardado

    if (!generarPdfFactura($it, $destPdfPath, $xmlContent)) {
        $errors[] = ['uuid' => $uuid, 'error' => 'Fallo al generar el PDF'];
        @unlink($destXmlPath); // Limpiamos el XML si el PDF falló
        continue;
    }
    // --- FIN: Generación del PDF ---


    // MODIFICADO: bind params (25 ahora, uno más para el pdf)
    $version = $it['version'] ?? '';
    $fecha = $it['fecha'] ?? null;
    $subtotal = $it['subtotal'] ?? 0.00;
    $total = $it['total'] ?? 0.00;
    $moneda = $it['moneda'] ?? '';
    $metodo_pago = $it['metodo_pago'] ?? '';
    $forma_pago = $it['forma_pago'] ?? '';
    $lugar_expedicion = $it['lugar_expedicion'] ?? '';
    $no_certificado = $it['no_certificado'] ?? '';
    $condiciones_pago = $it['condiciones_pago'] ?? '';
    $exportacion = $it['exportacion'] ?? '';
    $tipo_comprobante = $it['tipo_comprobante'] ?? '';
    $emisor_rfc = $it['emisor_rfc'] ?? '';
    $emisor_nombre = $it['emisor_nombre'] ?? '';
    $receptor_rfc = $it['receptor_rfc'] ?? '';
    $receptor_nombre = $it['receptor_nombre'] ?? '';
    $receptor_domicilio = $it['receptor_domicilio'] ?? '';
    $receptor_uso_cfdi = $it['receptor_uso_cfdi'] ?? '';
    $no_certificado_sat = $it['no_certificado_sat'] ?? '';
    $rfc_prov_certif = $it['rfc_prov_certif'] ?? '';
    $xml_file_db = $finalXmlName; // Solo el nombre del archivo
    $pdf_file_db = $finalPdfName; // Solo el nombre del archivo
    $serie = $it['serie'] ?? '';
    $folio = $it['folio'] ?? '';

    $ok = $stmt->bind_param(
        "sssssssssssssssssssssssss", // 
        $uuid,
        $version,
        $fecha,
        $subtotal,
        $total,
        $moneda,
        $metodo_pago,
        $forma_pago,
        $lugar_expedicion,
        $no_certificado,
        $condiciones_pago,
        $exportacion,
        $tipo_comprobante,
        $emisor_rfc,
        $emisor_nombre,
        $receptor_rfc,
        $receptor_nombre,
        $receptor_domicilio,
        $receptor_uso_cfdi,
        $no_certificado_sat,
        $rfc_prov_certif,
        $xml_file_db,
        $pdf_file_db,
        $serie,
        $folio
    );

    if (!$ok) {
        $errors[] = ['uuid' => $uuid, 'error' => 'Error bind_param: ' . $stmt->error];
        continue;
    }

    if ($stmt->execute()) {
        $inserted[] = ['uuid' => $uuid, 'emisor' => $emisor_nombre, 'total' => $total];
    } else {
        $errors[] = ['uuid' => $uuid, 'error' => 'Error execute: ' . $stmt->error];
        @unlink($destXmlPath);
        @unlink($destPdfPath); // Si falla la BD, también borramos el PDF
    }
}
$stmt->close();
$conn->close();

echo json_encode(['success' => count($errors) === 0, 'inserted' => $inserted, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
exit;
