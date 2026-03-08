<?php

use Glpi\Event;

// Requerido por GLPI. Funciones de ciclo de vida del plugin.

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/**
 * Desinstalación del plugin.
 * Elimina toda la configuración guardada en BD para garantizar
 * que la próxima instalación parta de defaults limpios.
 */
function plugin_responsivas_uninstall() {
   global $DB;

   // Borrar toda la configuración del plugin de la BD
   $DB->delete('glpi_configs', ['context' => 'plugin_responsivas']);

   // Registrar desinstalación en el log de eventos de GLPI
   Event::log(
      0,
      'plugin_responsivas',
      3,
      'plugin',
      'Plugin Responsivas desinstalado. Configuración eliminada.'
   );

   return true;
}
