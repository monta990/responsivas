<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas;

final class Paths
{
    public static function pluginDir(): string
    {
        if (defined('GLPI_PLUGINS_DIRECTORIES')) {
            foreach (GLPI_PLUGINS_DIRECTORIES as $dir) {
                $path = rtrim($dir, '/\\') . '/responsivas';
                if (is_dir($path)) {
                    return $path;
                }
            }
        }

        return dirname(__DIR__);
    }

    public static function webDir(): string
    {
        global $CFG_GLPI;
        return rtrim($CFG_GLPI['root_doc'] ?? '', '/') . '/plugins/responsivas';
    }

    public static function routeUrl(string $route = ''): string
    {
        $base = self::webDir();
        return $base . ($route !== '' ? '/' . ltrim($route, '/') : '');
    }

    public static function filesDir(): string
    {
        if (defined('GLPI_PLUGIN_DOC_DIR')) {
            return rtrim(GLPI_PLUGIN_DOC_DIR, '/\\') . '/responsivas';
        }
        return GLPI_ROOT . '/files/_plugins/responsivas';
    }

    public static function logoPath(): string
    {
        return self::filesDir() . '/logo.png';
    }

    public static function logoUrl(): string
    {
        return self::routeUrl('resource/logo');
    }
}
