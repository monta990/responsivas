<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

require_once __DIR__ . '/../inc/helpers.php';

// Solo POST — CSRF validado automáticamente por CheckCsrfListener de GLPI
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   http_response_code(405);
   exit;
}

Session::checkLoginUser();
if (!Session::haveRight('config', UPDATE)) {
   Session::addMessageAfterRedirect(__('Acceso denegado.', 'responsivas'), false, ERROR);
   Html::back();
   exit;
}

$redirect_url = Plugin::getWebDir('responsivas') . '/front/config.form.php';

// Correo del usuario logueado
$admin_id  = Session::getLoginUserID();
global $DB;
$email_row  = $DB->request([
   'FROM'  => 'glpi_useremails',
   'WHERE' => ['users_id' => $admin_id, 'is_default' => 1],
])->current();
$test_email = trim($email_row['email'] ?? '');

if (empty($test_email)) {
   Session::addMessageAfterRedirect(
      __('Tu usuario no tiene dirección de correo registrada en GLPI. Agrégala en tu perfil.', 'responsivas'),
      false, ERROR
   );
   Html::redirect($redirect_url);
   exit;
}

$config = Config::getConfigurationValues('plugin_responsivas');

$email_subject_tpl = trim($config['email_subject'] ?? '');
$email_body_tpl    = trim($config['email_body']    ?? '');
$email_footer_tpl  = trim($config['email_footer']  ?? '');

if (empty($email_subject_tpl) || empty($email_body_tpl)) {
   Session::addMessageAfterRedirect(
      __('Configuración de correo incompleta. Configure el asunto y cuerpo primero.', 'responsivas'),
      false, ERROR
   );
   Html::redirect($redirect_url);
   exit;
}

$admin_user = new User();
$admin_user->getFromDB($admin_id);

$vars = [
   '{nombre}'  => $admin_user->getFriendlyName(),
   '{empresa}' => $config['company_name'] ?? '',
   '{fecha}'   => (new DateTime('now', new DateTimeZone($config['timezone'] ?? date_default_timezone_get())))->format('d/m/Y'),
];

$email_subject = '[PRUEBA] ' . strtr($email_subject_tpl, $vars);
$email_body    = strtr($email_body_tpl,   $vars);
$email_footer  = strtr($email_footer_tpl, $vars);

$body_safe = nl2br(preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>',
   htmlspecialchars($email_body, ENT_QUOTES, 'UTF-8')));
$footer_safe = !empty($email_footer)
   ? '<hr style="margin-top:24px;border:none;border-top:1px solid #ddd;">'
     . '<p style="color:#888;font-size:11px;">' . nl2br(preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>',
        htmlspecialchars($email_footer, ENT_QUOTES, 'UTF-8'))) . '</p>'
   : '';

$notice_safe = htmlspecialchars(
   __('Este es un correo de prueba generado desde la configuración del plugin Responsivas. No se adjuntan PDFs.', 'responsivas'),
   ENT_QUOTES, 'UTF-8'
);

$body_html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#333;max-width:600px;margin:0 auto;padding:20px;">
  <p style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:10px;font-size:12px;color:#856404;">
    ⚠️ {$notice_safe}
  </p>
  <p>{$body_safe}</p>
  {$footer_safe}
</body>
</html>
HTML;

try {
   $mailer = new GLPIMailer();
   $email  = $mailer->getEmail();
   $email->subject($email_subject);
   $email->to($test_email);
   $email->html($body_html);
   $result = $mailer->send();

   if ($result) {
      Session::addMessageAfterRedirect(
         sprintf(__('Correo de prueba enviado correctamente a %s.', 'responsivas'), $test_email),
         false, INFO
      );
   } else {
      Session::addMessageAfterRedirect(
         __('El servidor de correo rechazó el envío.', 'responsivas'),
         false, ERROR
      );
   }
} catch (Throwable $e) {
   Session::addMessageAfterRedirect(
      sprintf(__('Error al enviar el correo: %s', 'responsivas'), $e->getMessage()),
      false, ERROR
   );
}

Html::redirect($redirect_url);
exit;
