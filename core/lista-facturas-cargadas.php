<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php'; // $conn

header('Content-Type: application/json; charset=utf-8');

$result = $conn->query("SELECT * FROM facturas ORDER BY fecha DESC LIMIT 50");

$facturas = [];

while ($row = $result->fetch_assoc()) {
    $row['subtotal'] = number_format($row['subtotal'], 2);
    $row['total'] = number_format($row['total'], 2);
    $row['pdf_url'] = 'uploads/pdf/' . $row['pdf_file'];
    $row['xml_url'] = 'uploads/xml/' . $row['xml_file'];
    $facturas[] = $row;
}

echo json_encode(['success' => true, 'data' => $facturas], JSON_UNESCAPED_UNICODE);
