<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// variables de sesión
$_SESSION['USR_ID']      = $_SESSION['USR_ID']      ?? '';
$_SESSION['USR_NAME']    = $_SESSION['USR_NAME']    ?? '';
$_SESSION['USR_TYPE']    = $_SESSION['USR_TYPE']    ?? '';
$_SESSION['USR_MAIL']    = $_SESSION['USR_MAIL']    ?? '';
$_SESSION['ERROR_MSG']   = $_SESSION['ERROR_MSG']   ?? '';
$_SESSION['DEFAULT_MSG'] = $_SESSION['DEFAULT_MSG'] ?? '';

define('VERSION', '1.0');
define('SYSNAME', 'Admin-SRJ');

$uri = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';

if (!defined('ABS_PATH')) {
    define('ABS_PATH', str_replace('\\', '/', dirname(__FILE__) . '/'));
}

if (!defined('HOMEURL')) {
    $base = 'localhost/app-m';
    define('HOMEURL', "$uri$base");
}

// DB
define('DB_NAME', 'sis_rje');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');

// conexión
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    // en lugar de morir con HTML, lanzamos excepción
    throw new Exception("Error en la conexión a la BD: " . $conn->connect_error);
}
$conn->set_charset("utf8");
