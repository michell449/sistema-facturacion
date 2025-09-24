<?php
ob_start();
session_start();
if (!isset($_SESSION)) {
    session_start();
    $_SESSION['USR_ID'] = '';
    $_SESSION['USR_NAME'] = '';
    $_SESSION['USR_TYPE'] = '';
    $_SESSION['USR_MAIL'] = ''; 
    $_SESSION['ERROR_MSG'] = '';
    $_SESSION['DEFAULT_MSG'] = '';
}

// Check if SSL
if (!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'])) {
    $uri = 'https://';
} else {
    $uri = 'http://';
}

define('VERSION', '1.0');
define('SYSNAME', 'Admin-SRJ');

if (!defined('ABS_PATH')) {
    define('ABS_PATH', str_replace('\\', '/', dirname(__FILE__) . '/'));
}

if (!defined('HOMEURL')) {
    // URL donde se aloja la aplicacion 
    $base = 'localhost/app-m';
    define('HOMEURL', "$uri$base");
}

if (!defined('PLANTILLAS_CORREO')) {
    define('PLANTILLAS_CORREO', ABS_PATH . "/core/mail/plantillas/");
}




/**
 * Parámetros del MySQL  
 */
define('MULTISITE', 0);

/** MySQL database name */
define('DB_NAME', 'facturacion');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Table prefix */
define('DB_TABLE_PREFIX', '');

/**
 * Parámetros del Correo electrónico  
 */
define('MAIL_HOST', 'smtp.ionos.mx');
define('MAIL_PORT', 587);
define('MAIL_USER', 'noreply@xube.com.mx');
define('MAIL_PSWD', '@Xub3*761');
define('MAIL_AUT', TRUE);
define('MAIL_SEC', 'tls');




date_default_timezone_set('America/Mexico_City');
if (!isset($_SESSION['USR_ID'])) {
    $_SESSION['USR_ID'] = '';
}
if (!isset($_SESSION['USR_NAME'])) {
    $_SESSION['USR_NAME'] = '';
}

if (!isset($_SESSION['USR_MAIL'])) {
    $_SESSION['USR_MAIL'] = '';
}

if (!isset($_SESSION['USR_TYPE'])) {
    $_SESSION['USR_TYPE'] = '';
}
if (!isset($_SESSION['ERROR_MSG'])) {
    $_SESSION['ERROR_MSG'] = '';
}
if (!isset($_SESSION['DEFAULT_MSG'])) {
    $_SESSION['DEFAULT_MSG'] = '';
}

$appPath = HOMEURL;

/**
 * Conexión a MySQL
 */
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Error en la conexión a la BD: " . $conn->connect_error);
}

$conn->set_charset("utf8");


