<?php
// core/actualizar-estado-solicitud.php

// SOLO procesar si es una petición POST AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Configurar headers para JSON
    header('Content-Type: application/json; charset=utf-8');
    
    // Incluir archivos necesarios
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/class/db.php';
    
    // Obtener y validar datos
    $input = json_decode(file_get_contents('php://input'), true);
    $idSolicitud = isset($input['id_solicitud']) ? (int)$input['id_solicitud'] : 0;
    
    if ($idSolicitud <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de solicitud inválido']);
        exit;
    }
    
    try {
        // Conectar a la base de datos
        $db = (new Database())->getConnection();
        
        // Consultar la solicitud
        $stmt = $db->prepare('SELECT id_solicitud, estado, paquetes_json, total_paquetes FROM cf_solicitudes WHERE id_solicitud = ?');
        $stmt->execute([$idSolicitud]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            exit;
        }
        
        // Procesar información de paquetes
        $paquetesDescargados = 0;
        $totalPaquetes = (int)$solicitud['total_paquetes'];
        
        if (!empty($solicitud['paquetes_json'])) {
            $paquetesData = json_decode($solicitud['paquetes_json'], true);
            if (is_array($paquetesData)) {
                $totalPaquetes = $totalPaquetes ?: count($paquetesData);
                foreach ($paquetesData as $paquete) {
                    if (isset($paquete['estado']) && in_array($paquete['estado'], ['descargado', 'procesado'])) {
                        $paquetesDescargados++;
                    }
                }
            }
        }
        
        // Devolver respuesta JSON
        echo json_encode([
            'success' => true,
            'id_solicitud' => $solicitud['id_solicitud'],
            'estado' => $solicitud['estado'],
            'total_paquetes' => $totalPaquetes,
            'paquetes_descargados' => $paquetesDescargados,
            'message' => 'Estado obtenido correctamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error en actualizar-estado-solicitud.php: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error del servidor: ' . $e->getMessage()
        ]);
    }
}
    exit; // Importante: salir después de enviar JSON


// Si no es POST, no hacer nada (no enviar HTML)