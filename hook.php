<?php

use Glpi\Event;

// Required by GLPI. Plugin lifecycle functions.

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/**
 * Plugin uninstall.
 * Removes all configuration stored in the database to ensure
 * a clean slate on the next installation.
 */
function plugin_responsivas_uninstall() {
   global $DB;

   $DB->delete('glpi_configs', ['context' => 'plugin_responsivas']);

   Event::log(
      0,
      'plugin_responsivas',
      3,
      'plugin',
      __('Responsivas plugin uninstalled. Configuration deleted.', 'responsivas')
   );

   return true;
}
