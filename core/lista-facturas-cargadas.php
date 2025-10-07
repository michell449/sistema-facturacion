<?php
// app-m/core/lista-facturas-cargadas.php
require_once __DIR__ . "/../config.php";
header('Content-Type: application/json; charset=utf-8');
$sql = "SELECT * FROM facturas ORDER BY fecha DESC";
$result = $conn->query($sql);
// Manejador de errores controlado (para no romper JSON)
mysqli_report(MYSQLI_REPORT_OFF);

try {
    // Si se llama con POST, se está intentando guardar facturas
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/guardar-facturas.php';
        exit;
    }

    // Consulta todas las facturas registradas
    $sql = "SELECT uuid, serie, folio, emisor_rfc, receptor_rfc, emisor_nombre, fecha, receptor_uso_cfdi, subtotal, total, forma_pago, metodo_pago, pdf_file, xml_file
            FROM facturas
            ORDER BY fecha DESC";

    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Error en la consulta: ' . $conn->error
        ]);
        exit;
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Excepción capturada: ' . $e->getMessage()
    ]);
}
