<?php

if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', dirname(__DIR__, 3));
}

require_once GLPI_ROOT . '/inc/includes.php';
require_once dirname(__DIR__) . '/inc/paths.class.php';

$resource = $_GET['resource'] ?? '';

if ($resource !== 'logo') {
   http_response_code(404);
   exit;
}

$path = PluginResponsivasPaths::logoPath();

if (!is_readable($path)) {
   http_response_code(404);
   exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;