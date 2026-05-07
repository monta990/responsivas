<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/**
 * Centraliza rutas físicas y URLs del plugin Responsivas.
 * Compatible con:
 * - GLPI 10
 * - GLPI 11
 * - GLPI 12
 * - Marketplace
 * - /plugins clásico
 */
class PluginResponsivasPaths
{
   /**
    * Directorio físico raíz del plugin.
    *
    * @throws RuntimeException
    */
   public static function pluginDir(): string
   {
      if (defined('GLPI_PLUGINS_DIRECTORIES')) {
         foreach (GLPI_PLUGINS_DIRECTORIES as $dir) {
            $path = $dir . '/responsivas';
            if (is_dir($path)) {
               return $path;
            }
         }
      }

      return dirname(__DIR__);
   }

   /**
    * URL pública base del plugin.
    */
   public static function webDir(): string
   {
      if (defined('PLUGINS_WEB_DIR')) {
         return PLUGINS_WEB_DIR . '/responsivas';
      }

      global $CFG_GLPI;

      return rtrim($CFG_GLPI['root_doc'] ?? '', '/') . '/plugins/responsivas';
   }

   /**
    * Directorio de documentos / archivos persistentes.
    */
   public static function filesDir(): string
   {
      if (defined('GLPI_PLUGIN_DOC_DIR')) {
         return GLPI_PLUGIN_DOC_DIR . '/responsivas';
      }

      return GLPI_ROOT . '/files/_plugins/responsivas';
   }

   /**
    * Ruta física del logo.
    */
   public static function logoPath(): string
   {
      return self::filesDir() . '/logo.png';
   }

   /**
    * URL pública del logo.
    */
   public static function logoUrl(): string
   {
      return self::webDir() . '/front/resource.send.php?resource=logo';
   }
}