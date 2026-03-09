<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/**
 * Centraliza rutas físicas y URLs del plugin Responsivas.
 * Usa GLPI_PLUGINS_DIRECTORIES para localizar el plugin sin asumir
 * profundidad de directorio (funciona tanto en /plugins como en /marketplace).
 */
class PluginResponsivasPaths
{
   /**
    * Directorio físico raíz del plugin.
    * Busca en todos los directorios registrados por GLPI.
    *
    * @throws RuntimeException si no se encuentra el directorio.
    */
   public static function pluginDir(): string
   {
      foreach (GLPI_PLUGINS_DIRECTORIES as $dir) {
         $path = $dir . '/responsivas';
         if (is_dir($path)) {
            return $path;
         }
      }
      // Fallback seguro: directorio del archivo actual
      return dirname(__DIR__);
   }

   /**
    * Directorio de archivos generados (logos, temporales).
    * Usa la constante GLPI_PLUGIN_DOC_DIR, que GLPI define correctamente
    * independientemente de dónde esté instalado el servidor.
    */
   public static function filesDir(): string
   {
      return GLPI_PLUGIN_DOC_DIR . '/responsivas';
   }

   /**
    * Ruta física del logo del plugin.
    */
   public static function logoPath(): string
   {
      return self::filesDir() . '/logo.png';
   }

   /**
    * URL pública del logo (servida a través del front seguro).
    */
   public static function logoUrl(): string
   {
      return Plugin::getWebDir('responsivas') . '/front/resource.send.php?resource=logo';
   }
}
