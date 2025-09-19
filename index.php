<?php
require_once 'config.php';

if (!isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60)
    $_SESSION['last_access'] = time();

$uri .= $_SERVER['HTTP_HOST'];
$base = dirname($_SERVER['PHP_SELF']);
$appPath = "$uri$base";
$pagePath = substr($_SERVER['REQUEST_URI'], strlen($base));
$pagePath = explode('?', $pagePath);
$pagePath = $pagePath[0];

// Trim any leading forward slash
$pagePath = trim($pagePath, "/");
$pagePath = str_replace(".php", "", $pagePath);

if ($pagePath == 'index') {
    $pagePath = '';
}

if (empty($pagePath)) {
   if ($_SESSION['USR_ID'] == '') {
      $pagePath = 'login';
  } else {
      $pagePath = 'panel';
    }
}
$pageInclude = "pages/$pagePath.inc.php";
//Page not found
if (!file_exists($pageInclude)) {
    $pagePath = '404';
    $pageInclude = "pages/$pagePath.inc.php";
}

$pageTitle = str_replace('/', ' ', $pagePath);
$pageTitle = ucfirst($pageTitle);

require_once 'pages/head.inc.php';

if ($pagePath == 'panel'){
   echo '<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">';
}else {
   echo '<body class="login-page bg-body-secondary">';
}
require_once $pageInclude;
require_once 'pages/script.inc.php';
ob_end_flush();
?>