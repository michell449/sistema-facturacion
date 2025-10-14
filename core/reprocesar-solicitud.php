<?php
// core/reprocesar-solicitud.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

// Argumento 1: ID de la solicitud
$idSolicitud = $argv[1] ?? 0;
$idSolicitud = (int) $idSolicitud;

// Argumento 2: Confirmación
$confirmacion = $argv[2] ?? '';

if ($idSolicitud <= 0) {
    die("ERROR: Debes proporcionar un id_solicitud válido.\nUso: php core/reprocesar-solicitud.php <ID_SOLICITUD>\n");
}

// Si no se ha confirmado, mostrar advertencia y salir.
if ($confirmacion !== '--confirmar') {
    echo "\n";
    echo "*******************************************************************************\n";
    echo "ADVERTENCIA: Estás a punto de reiniciar el estado de TODOS los paquetes         \n";
    echo "para la solicitud con ID {$idSolicitud}. Esto hará que se intenten descargar de nuevo.\n";
    echo "*******************************************************************************\n\n";
    echo "--> Si estás seguro, ejecuta el siguiente comando exactamente como se muestra:\n\n";
    echo "    php core/reprocesar-solicitud.php {$idSolicitud} --confirmar\n\n";
    exit; // Salir sin hacer nada
}

// --- Si llega aquí, es porque se usó --confirmar ---
echo "Confirmación recibida. Procesando solicitud {$idSolicitud}...\n";

try {
    $db = (new Database())->getConnection();

    // 1. Obtener la solicitud
    $stmt = $db->prepare("SELECT paquetes_json FROM cf_solicitudes WHERE id_solicitud = ?");
    $stmt->execute([$idSolicitud]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        die("ERROR: No se encontró la solicitud con el ID: {$idSolicitud}\n");
    }

    $paquetes = json_decode($solicitud['paquetes_json'], true);
    if (!is_array($paquetes) || empty($paquetes)) {
        die("La solicitud {$idSolicitud} no tiene paquetes o el formato es incorrecto.\n");
    }

    $nuevosPaquetes = [];
    $paquetesReiniciados = 0;

    // 2. Iterar y reiniciar todos los paquetes al estado 'pendiente'
    foreach ($paquetes as $paquete) {
        $paquete['estado'] = 'pendiente';
        $paquete['zip_path'] = null;
        $paquete['mensaje_error'] = null;
        $paquete['fecha_descarga'] = null;
        $nuevosPaquetes[] = $paquete;
        $paquetesReiniciados++;
    }

    if ($paquetesReiniciados > 0) {
        // 3. Actualizar la base de datos y el estado de la solicitud
        $updateStmt = $db->prepare("UPDATE cf_solicitudes SET paquetes_json = ?, estado = 'aceptada' WHERE id_solicitud = ?");
        $updateStmt->execute([json_encode($nuevosPaquetes), $idSolicitud]);
        echo "\nÉXITO: Se han reiniciado {$paquetesReiniciados} paquetes para la solicitud {$idSolicitud}.\n";
        echo "Ahora puedes ejecutar de nuevo el proceso de descarga para esta solicitud.\n";
    } else {
        echo "No se encontraron paquetes para reiniciar en la solicitud {$idSolicitud}.\n";
    }

} catch (Throwable $e) {
    die('ERROR CRÍTICO: ' . $e->getMessage() . "\n");
}