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
   responsivasErrorAndBack(__('No tienes permiso para generar responsivas de este usuario.', 'responsivas'));
}

/* ============================
 * Usuario
 * ============================ */
$user_id = (int)($_GET['users_id'] ?? $_GET['id'] ?? 0);
if ($user_id <= 0) {
   responsivasErrorAndBack(__('Usuario inválido.', 'responsivas'));
}

/* ============================
 * Generar PDF y enviar al navegador
 * ============================ */
try {
   $result = PluginResponsivasPdfBuilder::buildPhonePdf($user_id);
   $result['pdf']->Output($result['filename'], 'I');
} catch (RuntimeException $e) {
   responsivasErrorAndBack($e->getMessage());
}

exit;
