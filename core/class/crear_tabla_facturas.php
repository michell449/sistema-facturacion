<?php
require_once __DIR__ . '/class/db.php';
header('Content-Type: text/html; charset=utf-8');

try {
    $db = (new Database())->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS facturas (
        uuid               VARCHAR(50) NOT NULL PRIMARY KEY,
        version            VARCHAR(5) DEFAULT NULL,
        fecha              DATETIME DEFAULT NULL,
        subtotal           DECIMAL(12,2) DEFAULT NULL,
        total              DECIMAL(12,2) DEFAULT NULL,
        moneda             VARCHAR(10) DEFAULT NULL,
        metodo_pago        VARCHAR(5) DEFAULT NULL,
        forma_pago         VARCHAR(5) DEFAULT NULL,
        lugar_expedicion   VARCHAR(10) DEFAULT NULL,
        no_certificado     VARCHAR(50) DEFAULT NULL,
        condiciones_pago   VARCHAR(100) DEFAULT NULL,
        exportacion        VARCHAR(5) DEFAULT NULL,
        tipo_comprobante   VARCHAR(5) DEFAULT NULL,
        emisor_rfc         VARCHAR(13) DEFAULT NULL,
        emisor_nombre      VARCHAR(255) DEFAULT NULL,
        emisor_regimen     VARCHAR(5) DEFAULT NULL,
        receptor_rfc       VARCHAR(13) DEFAULT NULL,
        receptor_nombre    VARCHAR(255) DEFAULT NULL,
        receptor_domicilio VARCHAR(20) DEFAULT NULL,
        receptor_regimen   VARCHAR(5) DEFAULT NULL,
        receptor_uso_cfdi  VARCHAR(5) DEFAULT NULL,
        no_certificado_sat VARCHAR(50) DEFAULT NULL,
        rfc_prov_certif    VARCHAR(20) DEFAULT NULL,
        xml_file           VARCHAR(255) DEFAULT NULL,
        pdf_file           VARCHAR(255) DEFAULT NULL,
        serie              VARCHAR(10) DEFAULT NULL,
        folio              VARCHAR(10) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $db->exec($sql);
    echo " Tabla 'facturas' lista / actualizada correctamente.";
} catch (Throwable $e) {
    http_response_code(500);
    echo " Error: " . $e->getMessage();
}
?>
