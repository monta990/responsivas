<?php
if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}
require_once dirname(__DIR__) . '/inc/paths.class.php';
require_once dirname(__DIR__) . '/inc/config.class.php';
global $CFG_GLPI;
$self = $CFG_GLPI['root_doc'] . '/plugins/responsivas/front/config.form.php';