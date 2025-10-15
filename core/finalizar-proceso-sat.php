<?php
// finalizar-proceso.php
require_once __DIR__ . '/config.php'; // Para iniciar la sesión


$rfc_a_limpiar = 'ASY120705C39'; // Deberías obtenerlo de la solicitud o del usuario

if (isset($_SESSION['fiel_data'][$rfc_a_limpiar])) {
    unset($_SESSION['fiel_data'][$rfc_a_limpiar]);
    echo "FIEL para $rfc_a_limpiar eliminada de la sesión.";
}