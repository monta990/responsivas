<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/pdfbuilder.class.php';

/* ============================
 * Seguridad
 * ============================ */
Session::checkLoginUser();

if (!Session::haveRight('user', READ)) {
   responsivasErrorAndBack(__('You do not have permission to generate responsibility documents for this user.', 'responsivas'));
}

/* ============================
 * Usuario
 * ============================ */
$user_id = (int)($_GET['users_id'] ?? $_GET['id'] ?? 0);
if ($user_id <= 0) {
   responsivasErrorAndBack(__('Invalid user.', 'responsivas'));
}

$user = new User();
if (!$user->getFromDB($user_id) || !$user->canView()) {
   responsivasErrorAndBack(__('You do not have permission to generate responsibility documents for this user.', 'responsivas'));
}

/* ============================
 * Generar PDF y enviar al navegador
 * ============================ */
try {
   $result = PluginResponsivasPdfBuilder::buildPhonePdf($user_id);
   Log::history($user_id, 'User', [0, '', ''], 'responsivas: phone PDF downloaded', Log::HISTORY_LOG_SIMPLE_MESSAGE);
   $result['pdf']->Output($result['filename'], 'I');
} catch (RuntimeException $e) {
   responsivasErrorAndBack($e->getMessage());
}

exit;
