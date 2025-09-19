<!-- Panel principal de la aplicación usuarios -->
 <?php
if ($_SESSION['USR_TYPE'] == '') {
    // Destruyendo la session 
    session_start();
    session_destroy();
    header("Location: " . HOMEURL);
}

$pg = 'dashboard';

if (isset($_GET["pg"])) {
    $pg = $_GET["pg"];
}


echo '<div class="app-wrapper">'; //--begin::App Wrapper--
require_once 'pages/app-header.inc.php';
require_once 'pages/app-sidebar.inc.php';
require_once 'pages/app-main.inc.php';
require_once 'pages/app-footer.inc.php';
echo '</div>'   //!--end::App Wrapper-->

 ?>