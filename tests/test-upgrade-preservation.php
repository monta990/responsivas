<?php
declare(strict_types=1);

/**
 * Regression test for plugin_responsivas_install()/update() configuration
 * migration and the external logo/Twig-cache lifecycle.
 */
require_once __DIR__ . '/fixtures/glpi-stubs.php';

$root = dirname(__DIR__);
$testRoot = sys_get_temp_dir() . '/responsivas-upgrade-' . bin2hex(random_bytes(6));
$pluginDocs = $testRoot . '/plugin-docs';
$cacheRoot = $testRoot . '/cache';

if (!mkdir($pluginDocs, 0750, true) || !mkdir($cacheRoot, 0750, true)) {
    throw new RuntimeException('Could not create isolated upgrade-test directories.');
}

define('GLPI_ROOT', $root);
define('GLPI_PLUGIN_DOC_DIR', $pluginDocs);
define('GLPI_CACHE_DIR', $cacheRoot);

require $root . '/hook.php';

$removeDirectory = static function (string $directory) use (&$removeDirectory): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            $removeDirectory($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($directory);
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    Config::$values = [
        'plugin_responsivas' => [
            'company_name' => 'Customer Company',
            'pc_titulo' => 'Customer responsibility title',
            'pc_enable_visual_inspection' => 0,
            'config_schema_version' => PLUGIN_RESPONSIVAS_SCHEMA_VERSION,
        ],
    ];

    // Initial install fills absent keys but must retain an administrator's
    // existing values. The default logo is copied only if no custom logo exists.
    $assert(plugin_responsivas_install() === true, 'Plugin installation hook should succeed.');

    $config = Config::getConfigurationValues('plugin_responsivas');
    $assert(($config['company_name'] ?? null) === 'Customer Company', 'Company name was overwritten during install.');
    $assert(($config['pc_titulo'] ?? null) === 'Customer responsibility title', 'Document template was overwritten during install.');
    $assert(($config['pc_enable_visual_inspection'] ?? null) === 0, 'Disabled manual form setting was not preserved.');
    $assert(isset($config['pho_return_title']), 'New manual-form defaults were not created.');

    $logoPath = $pluginDocs . '/responsivas/logo.png';
    $assert(is_file($logoPath), 'The default institutional logo was not initialized.');
    $customLogo = "custom-logo-content\n";
    file_put_contents($logoPath, $customLogo);

    // An update with the current schema must not reset settings or replace the
    // custom logo. It must add only missing schema keys.
    $assert(plugin_responsivas_update('1.6.5', '1.6.6') === true, 'Plugin update hook should succeed.');
    $config = Config::getConfigurationValues('plugin_responsivas');
    $assert(($config['company_name'] ?? null) === 'Customer Company', 'Update overwrote the company name.');
    $assert(($config['pc_titulo'] ?? null) === 'Customer responsibility title', 'Update overwrote a customized document template.');
    $assert(($config['pc_enable_visual_inspection'] ?? null) === 0, 'Update re-enabled a disabled manual form.');
    $assert(file_get_contents($logoPath) === $customLogo, 'Update overwrote the administrator logo.');

    // Simulate an older schema. Keep-fields survive; reset-fields return to
    // their declared default, exactly as the lifecycle contract documents.
    Config::$values['plugin_responsivas'] = [
        'company_name' => 'Preserve this company',
        'pc_titulo' => 'Old schema custom title',
        'pc_enable_visual_inspection' => 0,
        'config_schema_version' => '0',
    ];

    plugin_responsivas_migrateConfig(Config::getConfigurationValues('plugin_responsivas'));
    $config = Config::getConfigurationValues('plugin_responsivas');
    $defaults = plugin_responsivas_getDefaults();

    $assert(($config['company_name'] ?? null) === 'Preserve this company', 'Schema migration overwrote a keep-field value.');
    $assert(($config['pc_enable_visual_inspection'] ?? null) === 0, 'Schema migration overwrote a keep-field manual-form switch.');
    $assert(($config['pc_titulo'] ?? null) === ($defaults['pc_titulo'] ?? null), 'Schema migration did not reset the field marked reset.');
    $assert(($config['config_schema_version'] ?? null) === PLUGIN_RESPONSIVAS_SCHEMA_VERSION, 'Schema version did not advance.');

    // Cache cleanup is scoped to Responsivas Twig cache only.
    $twigCache = $cacheRoot . '/responsivas_twig/nested';
    mkdir($twigCache, 0750, true);
    file_put_contents($twigCache . '/compiled.php', 'stale twig cache');
    file_put_contents($cacheRoot . '/keep.marker', 'unrelated cache');

    plugin_responsivas_clearCaches();

    $assert(!is_file($twigCache . '/compiled.php'), 'Responsivas Twig cache was not invalidated.');
    $assert(is_file($cacheRoot . '/keep.marker'), 'Cache cleanup removed an unrelated file.');

    fwrite(STDOUT, "Upgrade preservation test passed: schema keep/reset policy, logo persistence and cache isolation are valid.\n");
} finally {
    $removeDirectory($testRoot);
}
