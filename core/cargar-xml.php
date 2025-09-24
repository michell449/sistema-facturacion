<?php
require_once "config.php";

// Carpeta para almacenar archivos
$uploadDir = __DIR__ . "/uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["xmlFile"])) {
    $fileTmp = $_FILES["xmlFile"]["tmp_name"];
    $fileName = uniqid() . "_" . basename($_FILES["xmlFile"]["name"]);
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($fileTmp, $filePath)) {
        // Cargar XML
        $xml = simplexml_load_file($filePath);

        if ($xml === false) {
            die("Error al leer el XML.");
        }

        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('cfdi', $namespaces['cfdi']);
        $xml->registerXPathNamespace('tfd', $namespaces['tfd']);

        // Datos del CFDI
        $comprobante = $xml->attributes();
        $emisor = $xml->xpath('//cfdi:Emisor')[0]->attributes();
        $receptor = $xml->xpath('//cfdi:Receptor')[0]->attributes();
        $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0]->attributes();

        $uuid = (string) $timbre['UUID'];
        $version = (string) $comprobante['Version'];
        $serie = (string) $comprobante['Serie'];
        $folio = (string) $comprobante['Folio'];
        $fecha = (string) $comprobante['Fecha'];
        $subtotal = (string) $comprobante['SubTotal'];
        $total = (string) $comprobante['Total'];
        $moneda = (string) $comprobante['Moneda'];
        $metodo_pago = (string) $comprobante['MetodoPago'];
        $forma_pago = (string) $comprobante['FormaPago'];
        $lugar_expedicion = (string) $comprobante['LugarExpedicion'];
        $no_certificado = (string) $comprobante['NoCertificado'];
        $condiciones_pago = (string) $comprobante['CondicionesDePago'];
        $exportacion = (string) $comprobante['Exportacion'];
        $tipo_comprobante = (string) $comprobante['TipoDeComprobante'];

        $emisor_rfc = (string) $emisor['Rfc'];
        $emisor_nombre = (string) $emisor['Nombre'];
        $emisor_regimen = (string) $emisor['RegimenFiscal'];

        $receptor_rfc = (string) $receptor['Rfc'];
        $receptor_nombre = (string) $receptor['Nombre'];
        $receptor_domicilio = (string) $receptor['DomicilioFiscalReceptor'];
        $receptor_regimen = (string) $receptor['RegimenFiscalReceptor'];
        $receptor_uso_cfdi = (string) $receptor['UsoCFDI'];

        $fecha_timbrado = (string) $timbre['FechaTimbrado'];
        $no_certificado_sat = (string) $timbre['NoCertificadoSAT'];
        $rfc_prov_certif = (string) $timbre['RfcProvCertif'];

        // Guardar en BD
        $stmt = $conn->prepare("
            INSERT INTO facturas (
                uuid, version, serie, folio, fecha, subtotal, total, moneda, metodo_pago, forma_pago,
                lugar_expedicion, no_certificado, condiciones_pago, exportacion, tipo_comprobante,
                emisor_rfc, emisor_nombre, emisor_regimen,
                receptor_rfc, receptor_nombre, receptor_domicilio, receptor_regimen, receptor_uso_cfdi,
                fecha_timbrado, no_certificado_sat, rfc_prov_certif, xml_file
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssdsssssssssssssssssssss",
            $uuid, $version, $serie, $folio, $fecha, $subtotal, $total, $moneda, $metodo_pago, $forma_pago,
            $lugar_expedicion, $no_certificado, $condiciones_pago, $exportacion, $tipo_comprobante,
            $emisor_rfc, $emisor_nombre, $emisor_regimen,
            $receptor_rfc, $receptor_nombre, $receptor_domicilio, $receptor_regimen, $receptor_uso_cfdi,
            $fecha_timbrado, $no_certificado_sat, $rfc_prov_certif, $fileName
        );

        if ($stmt->execute()) {
            echo "Factura registrada con UUID: $uuid";
        } else {
            echo "Error al guardar en la BD: " . $stmt->error;
        }

    } else {
        echo "Error al subir el archivo.";
    }
}
