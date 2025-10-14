<?php
require_once __DIR__ . '/class/db.php';
header('Content-Type: text/html; charset=utf-8');
try {
    $db = (new Database())->getConnection();
    // Esquema mÃ­nimo: seguimiento de solicitudes + paquetes embebidos
    $sql = "CREATE TABLE IF NOT EXISTS cf_solicitudes (
        id_solicitud INT AUTO_INCREMENT PRIMARY KEY,
        solicitud_id_sat VARCHAR(120) NOT NULL,
        rfc_emisor VARCHAR(20) NOT NULL,
        rfc_receptor VARCHAR(20) NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_terminada DATETIME NULL,
        ultima_verificacion DATETIME NULL,
        estado ENUM('pendiente','aceptada','terminada','rechazada','error') DEFAULT 'pendiente',
        tipo ENUM('emitidas','recibidas') NOT NULL DEFAULT 'emitidas',
        folio VARCHAR(60) NULL,
        fecha_ini DATE NOT NULL,
        fecha_fin DATE NOT NULL,
        total_paquetes INT DEFAULT 0,
        total_cfdis INT DEFAULT 0,
        paquetes_json MEDIUMTEXT NULL,
        mensaje_error TEXT NULL,
        token VARCHAR(20) NULL,
        UNIQUE KEY uk_solicitud_sat (solicitud_id_sat),
        KEY idx_solicitudes_estado (estado),
        KEY idx_solicitudes_tipo (tipo),
        KEY idx_solicitudes_folio (folio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $db->exec($sql);
    echo "âœ… Tabla cf_solicitudes lista / actualizada";
    // Limpieza idempotente de columnas sobrantes si provienes de un esquema mÃ¡s grande
    $dropCols = ['fecha_aceptada','tipo_contenido','include_cancelados','rfc_emisor','rfc_receptor','descargados','procesados','cod_estatus','mensaje_sat'];
    foreach ($dropCols as $c) { try { $db->exec("ALTER TABLE cf_solicitudes DROP COLUMN $c"); } catch (Throwable $ign) { } }
    // Eliminar Ã­ndices ya innecesarios
    try { $db->exec("ALTER TABLE cf_solicitudes DROP INDEX idx_solicitudes_codestatus"); } catch (Throwable $ign) { }
} catch (Throwable $e) {
    http_response_code(500);
    echo "âŒ Error: " . $e->getMessage();
}
?>