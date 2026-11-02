<?php
declare(strict_types=1);

/**
 * Minimal GLPI compatibility shim for isolated unit tests.
 *
 * This bootstrap deliberately does not load or emulate GLPI internals. Tests
 * only exercise plugin utilities that can run independently of a GLPI server.
 */
$pluginRoot = dirname(__DIR__);

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', $pluginRoot);
}

if (!defined('GLPI_PLUGINS_DIRECTORIES')) {
    define('GLPI_PLUGINS_DIRECTORIES', []);
}

if (!function_exists('__')) {
    function __(string $message, ?string $domain = null): string
    {
        return $message;
    }
}

$GLOBALS['CFG_GLPI'] = ['root_doc' => '/glpi'];
$_SESSION = [];

require_once $pluginRoot . '/src/Paths.php';
require_once $pluginRoot . '/src/Utils.php';
