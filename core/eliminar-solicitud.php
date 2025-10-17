<?php
// core/eliminar-solicitud.php

header('Content-Type: application/json');

// Asegúrate de que la ruta a db.php sea correcta
require_once __DIR__ . '/class/db.php'; 

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// Verificar si se recibió el ID de la solicitud
if (!isset($_POST['id'])) {
    http_response_code(400);
    $response['message'] = 'ID de solicitud no proporcionado.';
    echo json_encode($response);
    exit;
}

$id_solicitud = filter_var($_POST['id'], FILTER_VALIDATE_INT);

if ($id_solicitud === false || $id_solicitud <= 0) {
    http_response_code(400);
    $response['message'] = 'ID de solicitud inválido.';
    echo json_encode($response);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    // 1. Verificar el estado antes de eliminar por seguridad
    $stCheck = $db->prepare("SELECT estado, paquetes_json FROM cf_solicitudes WHERE id_solicitud = ?");
    $stCheck->execute([$id_solicitud]);
    $solicitud = $stCheck->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        $response['message'] = "Solicitud con ID $id_solicitud no encontrada.";
        echo json_encode($response);
        exit;
    }
    
    // Verificar que solo se eliminen las rechazadas
    if ($solicitud['estado'] !== 'rechazada' && $solicitud['estado'] !== 'terminada') {
        http_response_code(403);
        $response['message'] = 'Solo se pueden eliminar solicitudes con estado "Rechazada".';
        echo json_encode($response);
        exit;
    }

    // 2. Intentar eliminar los archivos de paquete asociados (Limpieza)
    if (!empty($solicitud['paquetes_json'])) {
        $paquetes = json_decode($solicitud['paquetes_json'], true);
        if (is_array($paquetes)) {
            foreach ($paquetes as $p) {
                // Se asume que los paths de los archivos son relativos a la raíz del sistema (o donde se ejecuta el script)
                if (!empty($p['zip_path']) && file_exists(__DIR__ . '/../' . ltrim($p['zip_path'], '/\\'))) {
                    @unlink(__DIR__ . '/../' . ltrim($p['zip_path'], '/\\'));
                }
            }
        }
    }

    // 3. Eliminar la solicitud de la base de datos
    $st = $db->prepare("DELETE FROM cf_solicitudes WHERE id_solicitud = ?");
    $st->execute([$id_solicitud]);

    if ($st->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = "Solicitud eliminada correctamente.";
    } else {
        $response['message'] = "No se pudo eliminar la solicitud o ya no existe.";
    }

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = 'Error interno del servidor: ' . $e->getMessage();
}

echo json_encode($response);