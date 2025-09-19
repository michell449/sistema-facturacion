<?php
session_start();
include_once '../config.php';

// ----------------------------------------------
// Procesamiento de validación de login, condiciones de index.php y panel.php
// ----------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'], $_POST['password'])) {
    $correo = $_POST['email'];
    $contrasena = $_POST['password'];

    $usuarioValido = "admin@example.com";
    $contrasenaValida = "123456";

    if ($correo === $usuarioValido && $contrasena === $contrasenaValida) {
        $_SESSION['USR_ID'] = '1';
    $_SESSION['USR_NAME'] = 'Admin';
    $_SESSION['USR_TYPE'] = 'SA';
    $_SESSION['USR_MAIL'] = 'admin@example.com'; 
    $_SESSION['ERROR_MSG'] = '';
    $_SESSION['DEFAULT_MSG'] = '';

    } else {
        $_SESSION['USR_ID'] = '';
    $_SESSION['USR_NAME'] = '';
    $_SESSION['USR_TYPE'] = '';
    $_SESSION['USR_MAIL'] = ''; 
        $_SESSION['ERROR_MSG'] = "Las credenciales no son válidas";
    }
}

header('Location: ' . HOMEURL);

?>