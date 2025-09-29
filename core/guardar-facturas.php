<?php
// app-m/core/guardar-facturas.php
require_once __DIR__ . "/../config.php"; // $conn (mysqli)
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['items'])) {
    echo json_encode(['success'=>false,'message'=>'No hay facturas para guardar']);
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

// preparar statement (26 columnas como tu tabla)
$stmt = $conn->prepare("INSERT INTO facturas 
    (uuid, version, fecha, subtotal, total, moneda, metodo_pago, forma_pago, lugar_expedicion, no_certificado, condiciones_pago, exportacion, tipo_comprobante, emisor_rfc, emisor_nombre, emisor_regimen, receptor_rfc, receptor_nombre, receptor_domicilio, receptor_regimen, receptor_uso_cfdi, no_certificado_sat, rfc_prov_certif, xml_file, serie, folio)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

if (!$stmt) {
    echo json_encode(['success'=>false,'message'=>'Error al preparar consulta: '.$conn->error]);
    exit;
}

foreach ($items as $it) {
    // esperemos que cliente envíe el _tmp_file regresado por cargar-xml.php
    if (empty($it['_tmp_file'])) {
        $errors[] = ['uuid'=> $it['uuid'] ?? null, 'error'=>'Temp file faltante'];
        continue;
    }

    // revisar duplicado por uuid
    $uuid = $conn->real_escape_string($it['uuid']);
    $q = $conn->query("SELECT uuid FROM facturas WHERE uuid = '$uuid' LIMIT 1");
    if ($q && $q->num_rows > 0) {
        $errors[] = ['uuid'=>$uuid, 'error'=>'UUID ya existe en la base'];
        // eliminar tmp para no acumular (opcional)
        @unlink($uploadTmpDir . $it['_tmp_file']);
        continue;
    }

    // mover archivo temporal a xml final
    $tmpname = $uploadTmpDir . $it['_tmp_file'];
    $finalXmlName = $uuid . '.xml';
    $dest = $uploadXmlDir . $finalXmlName;
    if (!file_exists($tmpname) || !rename($tmpname, $dest)) {
        $errors[] = ['uuid'=>$uuid, 'error'=>'No se pudo mover xml a carpeta final'];
        continue;
    }

    // bind params (26 strings)
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
    $emisor_regimen = $it['emisor_regimen'] ?? '';
    $receptor_rfc = $it['receptor_rfc'] ?? '';
    $receptor_nombre = $it['receptor_nombre'] ?? '';
    $receptor_domicilio = $it['receptor_domicilio'] ?? '';
    $receptor_regimen = $it['receptor_regimen'] ?? '';
    $receptor_uso_cfdi = $it['receptor_uso_cfdi'] ?? '';
    $no_certificado_sat = $it['no_certificado_sat'] ?? '';
    $rfc_prov_certif = $it['rfc_prov_certif'] ?? '';
    $xml_file = 'xml/' . $finalXmlName; // guarda rutas relativas
    $serie = $it['serie'] ?? '';
    $folio = $it['folio'] ?? '';

    $ok = $stmt->bind_param(
        "ssssssssssssssssssssssssss",
        $uuid, $version, $fecha, $subtotal, $total, $moneda, $metodo_pago, $forma_pago, $lugar_expedicion, $no_certificado, $condiciones_pago, $exportacion, $tipo_comprobante,
        $emisor_rfc, $emisor_nombre, $emisor_regimen,
        $receptor_rfc, $receptor_nombre, $receptor_domicilio, $receptor_regimen, $receptor_uso_cfdi,
        $no_certificado_sat, $rfc_prov_certif, $xml_file, $serie, $folio
    );

    if (!$ok) {
        $errors[] = ['uuid'=>$uuid, 'error'=>'Error bind_param: '.$stmt->error];
        continue;
    }

    if ($stmt->execute()) {
        $inserted[] = $uuid;
    } else {
        $errors[] = ['uuid'=>$uuid, 'error'=>'Error execute: '.$stmt->error];
        // rollback parcial: intentar eliminar archivo movido
        @unlink($dest);
    }
}

echo json_encode(['success'=>count($errors)===0, 'inserted'=>$inserted, 'errors'=>$errors], JSON_UNESCAPED_UNICODE);
exit;
