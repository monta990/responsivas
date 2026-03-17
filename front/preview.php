<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', dirname(__DIR__, 3));
}

require_once GLPI_ROOT . '/inc/includes.php';
require_once dirname(__DIR__) . '/inc/paths.class.php';
require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/pdf.class.php';
require_once dirname(__DIR__) . '/inc/pdfbuilder.class.php';

// Vista previa — operación de solo lectura, no modifica estado
// Se acepta GET para evitar el CheckCsrfListener de GLPI 11
Session::checkLoginUser();
if (!Session::haveRight('config', UPDATE)) {
   Session::addMessageAfterRedirect(__('Acceso denegado.', 'responsivas'), false, ERROR);
   Html::back();
   exit;
}

$type    = $_GET['type'] ?? '';
$allowed = ['pc', 'pri', 'pho'];
if (!in_array($type, $allowed, true)) {
   http_response_code(400);
   exit;
}

$config     = Config::getConfigurationValues('plugin_responsivas');
$admin_id   = (int) Session::getLoginUserID();
$admin_user = new User();
$admin_user->getFromDB($admin_id);

try {
   $result = PluginResponsivasPdfBuilder::buildPreview($type, $admin_id, $config);
} catch (Throwable $e) {
   Session::addMessageAfterRedirect(
      sprintf(__('Error al generar la vista previa: %s', 'responsivas'), $e->getMessage()),
      false, ERROR
   );
   Html::back();
   exit;
}

/** @var PluginResponsivasPDF $pdf */
$pdf      = $result['pdf'];
$filename = 'Vista_Previa_' . $result['filename'];

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf->Output($filename, 'S');
exit;
