<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Responsivas\UserTab;

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/**
 * Responsivas — setup.php
 *
 * Bootstrap intentionally remains small. Runtime classes are PSR-4 loaded
 * from src/ by GLPI's plugin autoloader; HTTP entry points are Symfony
 * Controllers under src/Controller/.
 */
define('PLUGIN_RESPONSIVAS_VERSION', '1.6.1');
define('PLUGIN_RESPONSIVAS_MIN_GLPI', '11.0');
define('PLUGIN_RESPONSIVAS_MAX_GLPI', '12.99');

/**
 * Inicialización del plugin.
 */
function plugin_init_responsivas(): void
{
   global $PLUGIN_HOOKS;

   // GLPI 11 exposes this legacy hook; GLPI 12 removed it.
   if (version_compare(GLPI_VERSION, '12.0.0', '<')) {
      $PLUGIN_HOOKS['csrf_compliant']['responsivas'] = true;
   }

   // The configuration URL is resolved through the plugin Controller route.
   $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['responsivas'] = 'config';

   // Registration of the User tab uses the PSR-4 class directly.
   Plugin::registerClass(UserTab::class, ['addtabon' => ['User']]);
}

/**
 * Información del plugin
 */
function plugin_version_responsivas() {
   return [
      'name'          => 'Responsivas',
      'version'       => PLUGIN_RESPONSIVAS_VERSION,
      'author'        => 'Edwin Elias Alvarez',
      'license'       => 'GPLv3+',
      'homepage'      => 'https://github.com/monta990/responsivas',
      'minphpversion' => '8.2',
      'requirements'  => [
         'glpi' => [
            'min' => PLUGIN_RESPONSIVAS_MIN_GLPI,
            'max' => PLUGIN_RESPONSIVAS_MAX_GLPI,
         ],
      ],
   ];
}

/**
 * Verifica prerrequisitos antes de activar el plugin.
 * GLPI llama esta función automáticamente.
 */
function plugin_responsivas_check_prerequisites() {

   // PHP minimum 8.2
   if (version_compare(PHP_VERSION, '8.2', '<')) {
      echo '<div class="alert alert-danger">'
         . sprintf(__('Responsivas requires PHP 8.2 or higher. Current version: %s', 'responsivas'), PHP_VERSION)
         . '</div>';
      return false;
   }

   // Required PHP extensions for image processing, localized dates and JSON.
   foreach (['fileinfo', 'gd', 'intl', 'json'] as $extension) {
      if (!extension_loaded($extension)) {
         echo '<div class="alert alert-danger">'
            . sprintf(
               __('Responsivas requires the PHP extension %s.', 'responsivas'),
               $extension
            )
            . '</div>';
         return false;
      }
   }

   // TCPDF debe estar disponible (lo incluye GLPI en vendor)
   if (!class_exists('TCPDF') && !file_exists(GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
      echo '<div class="alert alert-danger">'
         . __('Responsivas requires TCPDF, which must be available in the GLPI vendor directory.', 'responsivas')
         . '</div>';
      return false;
   }

   return true;
}

