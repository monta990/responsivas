<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', $pluginRoot);
}

if (!defined('GLPI_PLUGINS_DIRECTORIES')) {
    define('GLPI_PLUGINS_DIRECTORIES', []);
}

$GLOBALS['CFG_GLPI'] = ['root_doc' => '/glpi'];
$_SESSION = [];

require_once __DIR__ . '/fixtures/glpi-stubs.php';
require_once $pluginRoot . '/src/Paths.php';
require_once $pluginRoot . '/src/Utils.php';
require_once $pluginRoot . '/src/Exception/MissingEntityLocationException.php';
require_once $pluginRoot . '/src/Pdf/PdfBuilder.php';
require_once $pluginRoot . '/src/Service/ConfigService.php';
