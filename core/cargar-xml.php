<?php
require_once __DIR__ . "/../config.php";

$uploadDir = __DIR__ . "/../uploads/";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["xmlFile"])) {
    $fileTmpPath = $_FILES["xmlFile"]["tmp_name"];
    $fileName = $_FILES["xmlFile"]["name"];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExtension !== "xml") {
        die("El archivo debe ser XML.");
    }

    $xml = simplexml_load_file($fileTmpPath);
    if ($xml === false) die("Error al leer el archivo XML.");

    $namespaces = $xml->getNamespaces(true);

    // Acceder al namespace cfdi de manera segura
    $cfdi = isset($namespaces['cfdi']) ? $xml->children($namespaces['cfdi']) : $xml;

    $comprobante = $cfdi->attributes();

    $folio = (string) $comprobante['Folio'];
    $fecha = (string) $comprobante['Fecha'];
    $total = (string) $comprobante['Total'];
    $emisor = isset($cfdi->Emisor) ? $cfdi->Emisor->attributes() : null;
    $receptor = isset($cfdi->Receptor) ? $cfdi->Receptor->attributes() : null;

    // Timbre fiscal digital
    $timbre = null;
    if (isset($cfdi->Complemento) && isset($namespaces['tfd'])) {
        $tfd = $cfdi->Complemento->children($namespaces['tfd']);
        if (isset($tfd->TimbreFiscalDigital)) {
            $timbre = $tfd->TimbreFiscalDigital->attributes();
        }
    }

    $uuid = $timbre ? (string)$timbre['UUID'] : '';
    $emisor_nombre = $emisor ? (string)$emisor['Nombre'] : '';
    $emisor_rfc = $emisor ? (string)$emisor['Rfc'] : '';
    $receptor_nombre = $receptor ? (string)$receptor['Nombre'] : '';
    $receptor_rfc = $receptor ? (string)$receptor['Rfc'] : '';

    // Guardar archivo
    move_uploaded_file($fileTmpPath, $uploadDir . $fileName);

    // Insertar en BD
    $stmt = $conn->prepare("
        INSERT INTO facturas (uuid, folio, fecha, total, emisor_rfc, emisor_nombre, receptor_rfc, receptor_nombre, xml_file)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssssss", $uuid, $folio, $fecha, $total, $emisor_rfc, $emisor_nombre, $receptor_rfc, $receptor_nombre, $fileName);

    if ($stmt->execute()) {
        header("Location: ../pages/cargar-facturas.inc.php?msg=ok&uuid=$uuid");
        exit();
    } else {
        die("Error al guardar en la BD: " . $stmt->error);
    }
} else {
    die("No se ha enviado ningún archivo.");
}