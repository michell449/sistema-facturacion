<?php
if (isset($_GET['pg'])) {
    $pagina = basename($_GET['pg']);
    $archivo = __DIR__ . "/$pagina.inc.php";
    if (file_exists($archivo)) {
        include $archivo;
    } else {
        include __DIR__ . "/404.inc.php";
    }
} else {
    include __DIR__ . "/panel.inc.php";
}
?>