<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

class PluginResponsivasTwig
{
    private static ?\Twig\Environment $env = null;

    public static function env(): \Twig\Environment
    {
        if (self::$env !== null) {
            return self::$env;
        }

        $tplDir   = PluginResponsivasPaths::pluginDir() . '/templates';
        $cacheDir = (defined('GLPI_CACHE_DIR') ? GLPI_CACHE_DIR : sys_get_temp_dir())
                  . '/responsivas_twig';

        $loader = new \Twig\Loader\FilesystemLoader($tplDir);

        self::$env = new \Twig\Environment($loader, [
            'cache'       => $cacheDir,
            'auto_reload' => true,
        ]);

        self::$env->addFunction(
            new \Twig\TwigFunction('trans', static fn(string $key, string $domain = 'responsivas') => __($key, $domain))
        );

        self::$env->addFilter(
            new \Twig\TwigFilter('trans', static fn(string $key, string $domain = 'responsivas') => __($key, $domain))
        );

        return self::$env;
    }
}
