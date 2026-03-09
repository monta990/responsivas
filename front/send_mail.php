<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/generator.class.php';

global $CFG_GLPI;

/* ============================
 * Solo POST permitido
 * ============================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   Html::redirect($CFG_GLPI['root_doc'] . '/front/user.php');
   exit;
}

/* ============================
 * Seguridad
 * ============================ */
Session::checkLoginUser();
// CSRF validado automáticamente por CheckCsrfListener de GLPI (token generado con Session::getNewCSRFToken())

if (!Session::haveRight('user', READ)) {
   Session::addMessageAfterRedirect(__('Acceso denegado.', 'responsivas'), false, ERROR);
   Html::redirect($CFG_GLPI['root_doc'] . '/front/user.php');
   exit;
}

/* ============================
 * Parámetros
 * ============================ */
$user_id = (int)($_POST['users_id'] ?? 0);

$redirect_user = static function (int $uid) use ($CFG_GLPI): void {
   Html::redirect($CFG_GLPI['root_doc'] . '/front/user.form.php?id=' . $uid . '&forcetab=PluginResponsivasUser$1');
};

if ($user_id <= 0) {
   Session::addMessageAfterRedirect(__('Usuario inválido.', 'responsivas'), false, ERROR);
   Html::redirect($CFG_GLPI['root_doc'] . '/front/user.php');
   exit;
}

/* ============================
 * Cargar usuario
 * ============================ */
$user = new User();
if (!$user->getFromDB($user_id)) {
   Session::addMessageAfterRedirect(__('Usuario no encontrado.', 'responsivas'), false, ERROR);
   $redirect_user($user_id);
   exit;
}

/* ============================
 * Email del usuario (glpi_useremails, no glpi_users)
 * ============================ */
global $DB;
$email_row  = $DB->request(['FROM' => 'glpi_useremails', 'WHERE' => ['users_id' => $user_id, 'is_default' => 1]])->current();
$user_email = trim($email_row['email'] ?? '');

if (empty($user_email)) {
   Session::addMessageAfterRedirect(
      __('El usuario no tiene dirección de correo electrónico registrada.', 'responsivas'),
      false,
      ERROR
   );
   $redirect_user($user_id);
   exit;
}

/* ============================
 * Configuración del plugin
 * ============================ */
$config = Config::getConfigurationValues('plugin_responsivas');

$email_subject_tpl = trim($config['email_subject'] ?? '');
$email_body_tpl    = trim($config['email_body']    ?? '');
$email_footer_tpl  = trim($config['email_footer']  ?? '');

if (empty($email_subject_tpl) || empty($email_body_tpl)) {
   Session::addMessageAfterRedirect(
      __('Configuración de correo incompleta. Configure el asunto y cuerpo en la pestaña Correo.', 'responsivas'),
      false,
      ERROR
   );
   $redirect_user($user_id);
   exit;
}

/* ============================
 * Sustitución de variables {nombre}, {empresa}, {fecha}
 * ============================ */
$vars = [
   '{nombre}'  => $user->getFriendlyName(),
   '{empresa}' => $config['company_name'] ?? '',
   '{fecha}'   => (new DateTime('now', new DateTimeZone($config['timezone'] ?? date_default_timezone_get())))->format('d/m/Y'),
];

$email_subject = strtr($email_subject_tpl, $vars);
$email_body    = strtr($email_body_tpl,    $vars);
$email_footer  = strtr($email_footer_tpl,  $vars);

/* ============================
 * Generar PDFs
 * ============================ */
$pdfs = PluginResponsivasGenerator::generateAll($user_id);

if (empty($pdfs)) {
   Session::addMessageAfterRedirect(
      __('No hay responsivas que generar para este usuario.', 'responsivas'),
      false,
      WARNING
   );
   $redirect_user($user_id);
   exit;
}

/* ============================
 * Construir cuerpo HTML del correo
 * ============================ */
$body_safe   = nl2br(preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>',
   htmlspecialchars($email_body, ENT_QUOTES, 'UTF-8')));
$footer_safe = !empty($email_footer)
   ? '<hr style="margin-top:24px;border:none;border-top:1px solid #ddd;">'
     . '<p style="color:#888;font-size:11px;">' . nl2br(preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', htmlspecialchars($email_footer, ENT_QUOTES, 'UTF-8'))) . '</p>'
   : '';

$count           = count($pdfs);
$attachment_note = sprintf(
   _n('Se adjunta %d responsiva PDF.', 'Se adjuntan %d responsivas PDF.', $count, 'responsivas'),
   $count
);

$body_html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#333;max-width:600px;margin:0 auto;padding:20px;">
  <p>{$body_safe}</p>
  <p style="color:#555;font-size:12px;margin-top:16px;">{$attachment_note}</p>
  {$footer_safe}
</body>
</html>
HTML;

/* ============================
 * Envío con GLPIMailer
 * ============================ */
try {
   $mailer = new GLPIMailer();
   $email  = $mailer->getEmail();

   $email->subject($email_subject);
   $email->to($user_email);
   $email->html($body_html);

   foreach ($pdfs as $pdf_item) {
      $email->attach($pdf_item['content'], $pdf_item['filename'], 'application/pdf');
   }

   $result = $mailer->send();

   if ($result) {
      Session::addMessageAfterRedirect(
         sprintf(__('Correo enviado correctamente a %s.', 'responsivas'), $user_email),
         false,
         INFO
      );

      // Registrar en el historial nativo del usuario en GLPI
      $quien = 'GLPI';
      if ($uid = Session::getLoginUserID()) {
         $u = new User();
         if ($u->getFromDB($uid)) $quien = $u->getFriendlyName();
      }
      Log::history(
         $user_id,
         'User',
         [0, '', sprintf(__('Responsivas enviadas por correo a %s por %s (%d PDF).', 'responsivas'), $user_email, $quien, $count)],
         0,
         Log::HISTORY_LOG_SIMPLE_MESSAGE
      );

   } else {
      Session::addMessageAfterRedirect(
         sprintf(__('Error al enviar el correo: %s', 'responsivas'), __('El servidor de correo rechazó el envío.')),
         false,
         ERROR
      );
   }

} catch (Throwable $e) {
   Session::addMessageAfterRedirect(
      sprintf(__('Error al enviar el correo: %s', 'responsivas'), $e->getMessage()),
      false,
      ERROR
   );
}

/* ============================
 * Redirigir al perfil del usuario
 * ============================ */
$redirect_user($user_id);
exit;
