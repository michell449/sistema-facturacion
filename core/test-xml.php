<?php
// test-xml.php
header('Content-Type: text/plain; charset=utf-8');

if (!isset($_FILES['xmlFile'])) {
    echo "Sube un CFDI para probar.\n";
    echo "Crea un formulario como:\n";
    echo '<form method="POST" enctype="multipart/form-data">';
    echo '<input type="file" name="xmlFile" accept=".xml">';
    echo '<button type="submit">Probar</button>';
    echo '</form>';
    exit;
}

$xmlPath = $_FILES['xmlFile']['tmp_name'];
if (!file_exists($xmlPath)) {
    die("No se encontró el archivo temporal");
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlPath);
if (!$xml) {
    echo "Error al leer el XML:\n";
    foreach (libxml_get_errors() as $error) {
        echo $error->message;
    }
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

echo "======= DATOS COMPROBANTE =======\n";
printf("Version: %s\n", (string)$comprobante['Version']);
printf("Serie: %s\n", (string)$comprobante['Serie']);
printf("Folio: %s\n", (string)$comprobante['Folio']);
printf("Fecha: %s\n", (string)$comprobante['Fecha']);
printf("SubTotal: %s\n", (string)$comprobante['SubTotal']);
printf("Total: %s\n", (string)$comprobante['Total']);
printf("Moneda: %s\n", (string)$comprobante['Moneda']);
printf("MetodoPago: %s\n", (string)$comprobante['MetodoPago']);
printf("FormaPago: %s\n", (string)$comprobante['FormaPago']);
printf("LugarExpedicion: %s\n", (string)$comprobante['LugarExpedicion']);
printf("CondicionesDePago: %s\n", (string)$comprobante['CondicionesDePago']);
printf("Exportacion: %s\n", (string)$comprobante['Exportacion']);
printf("TipoDeComprobante: %s\n", (string)$comprobante['TipoDeComprobante']);
printf("NoCertificado: %s\n", (string)$comprobante['NoCertificado']);

echo "\n======= EMISOR =======\n";
printf("RFC: %s\n", (string)$emisor['Rfc']);
printf("Nombre: %s\n", (string)$emisor['Nombre']);
printf("RegimenFiscal: %s\n", (string)$emisor['RegimenFiscal']);

echo "\n======= RECEPTOR =======\n";
printf("RFC: %s\n", (string)$receptor['Rfc']);
printf("Nombre: %s\n", (string)$receptor['Nombre']);
printf("UsoCFDI: %s\n", (string)$receptor['UsoCFDI']);
printf("DomicilioFiscalReceptor: %s\n", (string)$receptor['DomicilioFiscalReceptor']);
printf("RegimenFiscalReceptor: %s\n", (string)$receptor['RegimenFiscalReceptor']);

echo "\n======= TIMBRE FISCAL =======\n";
printf("UUID: %s\n", (string)$timbre['UUID']);
printf("NoCertificadoSAT: %s\n", (string)$timbre['NoCertificadoSAT']);
printf("RfcProvCertif: %s\n", (string)$timbre['RfcProvCertif']);
