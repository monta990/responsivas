<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', dirname(__DIR__, 3));
}

require_once GLPI_ROOT . '/inc/includes.php';
require_once dirname(__DIR__) . '/inc/paths.class.php';

Session::checkLoginUser();

$resource = $_GET['resource'] ?? '';

if ($resource !== 'logo') {
   http_response_code(404);
   exit;
}

$path = PluginResponsivasPaths::logoPath();

if (!is_file($path) || !is_readable($path)) {
   http_response_code(404);
   exit;
}

// MUY IMPORTANTE
while (ob_get_level() > 0) {
   ob_end_clean();
}

$mime = mime_content_type($path) ?: 'image/png';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;