<?php
require_once __DIR__ . "/config.php";
header('Content-Type: application/json');

if (!isset($_FILES['xmlFile'])) {
    echo json_encode(["success" => false, "message" => "No se recibió archivo"]);
    exit;
}

$xmlPath = $_FILES['xmlFile']['tmp_name'];
if (!file_exists($xmlPath)) {
    echo json_encode(["success" => false, "message" => "Archivo temporal no encontrado"]);
    exit;
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlPath);
if (!$xml) {
    echo json_encode(["success" => false, "message" => "Error al leer el archivo XML"]);
    exit;
}

// namespaces
$namespaces = $xml->getNamespaces(true);
$xml->registerXPathNamespace('cfdi', $namespaces['cfdi']);
$xml->registerXPathNamespace('tfd', $namespaces['tfd']);

// datos principales
$comprobante = $xml->xpath('//cfdi:Comprobante')[0];
$emisor      = $xml->xpath('//cfdi:Emisor')[0];
$receptor    = $xml->xpath('//cfdi:Receptor')[0];
$timbre      = $xml->xpath('//tfd:TimbreFiscalDigital')[0];

// atributos
$uuid       = (string) $timbre['UUID'];
$version    = (string) $comprobante['Version'];
$fecha      = (string) $comprobante['Fecha'];
$subtotal   = (string) $comprobante['SubTotal'];
$total      = (string) $comprobante['Total'];
$moneda     = (string) $comprobante['Moneda'];
$metodoPago = (string) $comprobante['MetodoPago'];
$formaPago  = (string) $comprobante['FormaPago'];
$lugarExp   = (string) $comprobante['LugarExpedicion'];
$noCert     = (string) $comprobante['NoCertificado'];
$condPago   = (string) $comprobante['CondicionesDePago'];
$exporta    = (string) $comprobante['Exportacion'];
$tipoComp   = (string) $comprobante['TipoDeComprobante'];
$serie      = (string) $comprobante['Serie'];
$folio      = (string) $comprobante['Folio'];

// emisor
$emisorRfc     = (string) $emisor['Rfc'];
$emisorNombre  = (string) $emisor['Nombre'];
$emisorRegimen = (string) $emisor['RegimenFiscal'];

// receptor
$receptorRfc       = (string) $receptor['Rfc'];
$receptorNombre    = (string) $receptor['Nombre'];
$receptorRegimen   = (string) $receptor['RegimenFiscalReceptor'];
$receptorUsoCfdi   = (string) $receptor['UsoCFDI'];
$receptorDomicilio = (string) $receptor['DomicilioFiscalReceptor'];

// timbre
$noCertSat   = (string) $timbre['NoCertificadoSAT'];
$rfcProvCert = (string) $timbre['RfcProvCertif'];

// guardar archivo en /uploads
$uploadDir = __DIR__ . "/../uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$xmlFileName = $uuid . ".xml";
move_uploaded_file($_FILES['xmlFile']['tmp_name'], $uploadDir . $xmlFileName);

// insertar en BD
$stmt = $conn->prepare("INSERT INTO facturas 
    (uuid, version, fecha, subtotal, total, moneda, metodo_pago, forma_pago, lugar_expedicion, no_certificado, condiciones_pago, exportacion, tipo_comprobante, emisor_rfc, emisor_nombre, emisor_regimen, receptor_rfc, receptor_nombre, receptor_domicilio, receptor_regimen, receptor_uso_cfdi, no_certificado_sat, rfc_prov_certif, xml_file, serie, folio)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$stmt->bind_param(
    "ssssssssssssssssssssssssss",
    $uuid, $version, $fecha, $subtotal, $total, $moneda, $metodoPago, $formaPago, $lugarExp, $noCert, $condPago, $exporta, $tipoComp,
    $emisorRfc, $emisorNombre, $emisorRegimen,
    $receptorRfc, $receptorNombre, $receptorDomicilio, $receptorRegimen, $receptorUsoCfdi,
    $noCertSat, $rfcProvCert, $xmlFileName, $serie, $folio
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Factura guardada correctamente"]);
} else {
    echo json_encode(["success" => false, "message" => "Error en BD: " . $stmt->error]);
}
