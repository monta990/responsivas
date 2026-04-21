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
   Session::addMessageAfterRedirect(__('Access denied.', 'responsivas'), false, ERROR);
   Html::redirect($CFG_GLPI['root_doc'] . '/front/user.php');
   exit;
}

/* ============================
 * Modo prueba (mode=test): envía a admin sin PDFs
 * ============================ */
if (($_POST['mode'] ?? '') === 'test') {

   if (!Session::haveRight('config', UPDATE)) {
      Session::addMessageAfterRedirect(__('Access denied.', 'responsivas'), false, ERROR);
      Html::back();
      exit;
   }

   $redirect_url = Plugin::getWebDir('responsivas') . '/front/config.form.php';
   $admin_id     = Session::getLoginUserID();
   global $DB;
   $email_row  = $DB->request([
      'FROM'  => 'glpi_useremails',
      'WHERE' => ['users_id' => $admin_id, 'is_default' => 1],
   ])->current();
   $test_email = trim($email_row['email'] ?? '');

   if (empty($test_email)) {
      Session::addMessageAfterRedirect(
         __('Your user does not have an email address registered in GLPI. Add it in your profile.', 'responsivas'),
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
         __('Incomplete email configuration. Configure the subject and body first.', 'responsivas'),
         false, ERROR
      );
      Html::redirect($redirect_url);
      exit;
   }

   $admin_user = new User();
   $admin_user->getFromDB($admin_id);

   // Valores HTML-escapados: el template ya viene escapado, los vars deben serlo también
   $vars = [
      '{nombre}'  => htmlspecialchars($admin_user->getFriendlyName(), ENT_QUOTES, 'UTF-8'),
      '{empresa}' => htmlspecialchars($config['company_name'] ?? '', ENT_QUOTES, 'UTF-8'),
      '{fecha}'   => htmlspecialchars((new DateTime('now', new DateTimeZone($config['timezone'] ?? date_default_timezone_get())))->format('d/m/Y'), ENT_QUOTES, 'UTF-8'),
   ];

   // responsivasApplyTemplate: ** * __ en orden correcto, luego sustituye variables
   $email_subject = '[PRUEBA] ' . strip_tags(responsivasApplyTemplate(htmlspecialchars($email_subject_tpl, ENT_QUOTES, 'UTF-8'), $vars));
   $body_safe     = nl2br(responsivasApplyTemplate(htmlspecialchars($email_body_tpl,    ENT_QUOTES, 'UTF-8'), $vars));
   $footer_raw    = responsivasApplyTemplate(htmlspecialchars($email_footer_tpl, ENT_QUOTES, 'UTF-8'), $vars);
   $footer_safe   = !empty(trim($email_footer_tpl))
      ? '<hr style="margin-top:24px;border:none;border-top:1px solid #ddd;">'
        . '<p style="color:#888;font-size:11px;">' . nl2br($footer_raw) . '</p>'
      : '';

   $notice_safe = htmlspecialchars(
      __('This is a test email generated from the Responsivas plugin configuration. No PDFs are attached.', 'responsivas'),
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
      global $CFG_GLPI;
      $fromName  = trim($CFG_GLPI['from_email_name']  ?? $CFG_GLPI['admin_email_name'] ?? '');
      $fromEmail = trim($CFG_GLPI['from_email']        ?? $CFG_GLPI['admin_email']      ?? '');
      if ($fromEmail !== '' && $fromName !== '') {
         $email->from(new \Symfony\Component\Mime\Address($fromEmail, $fromName));
      }
      $email->subject($email_subject);
      $email->to($test_email);
      $email->html($body_html);
      $result = $mailer->send();

      if ($result) {
         Session::addMessageAfterRedirect(
            sprintf(__('Test email successfully sent to %s.', 'responsivas'), $test_email),
            false, INFO
         );
      } else {
         Session::addMessageAfterRedirect(
            __('The mail server rejected the sending.', 'responsivas'),
            false, ERROR
         );
      }
   } catch (Throwable $e) {
      Session::addMessageAfterRedirect(
         sprintf(__('Error sending email: %s', 'responsivas'), $e->getMessage()),
         false, ERROR
      );
   }

   Html::redirect($redirect_url);
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
   Session::addMessageAfterRedirect(__('Invalid user.', 'responsivas'), false, ERROR);
   Html::redirect($CFG_GLPI['root_doc'] . '/front/user.php');
   exit;
}

/* ============================
 * Cargar usuario
 * ============================ */
$user = new User();
if (!$user->getFromDB($user_id) || !$user->canView()) {
   Session::addMessageAfterRedirect(__('User not found.', 'responsivas'), false, ERROR);
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
      __('The user does not have a registered email address.', 'responsivas'),
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
      __('Incomplete email configuration. Configure the subject and body in the Email tab.', 'responsivas'),
      false,
      ERROR
   );
   $redirect_user($user_id);
   exit;
}

/* ============================
 * Sustitución de variables {nombre}, {empresa}, {fecha}
 * ============================ */
// Valores HTML-escapados: el template ya viene escapado, los vars deben serlo también
$vars = [
   '{nombre}'  => htmlspecialchars($user->getFriendlyName(), ENT_QUOTES, 'UTF-8'),
   '{empresa}' => htmlspecialchars($config['company_name'] ?? '', ENT_QUOTES, 'UTF-8'),
   '{fecha}'   => htmlspecialchars((new DateTime('now', new DateTimeZone($config['timezone'] ?? date_default_timezone_get())))->format('d/m/Y'), ENT_QUOTES, 'UTF-8'),
];

// responsivasApplyTemplate: ** * __ en orden correcto, luego sustituye variables
$email_subject = strip_tags(responsivasApplyTemplate(htmlspecialchars($email_subject_tpl, ENT_QUOTES, 'UTF-8'), $vars));

/* ============================
 * Generar PDFs
 * ============================ */
// Selected document types (at least one must be checked)
$send_computers = !empty($_POST['send_computers']);
$send_printers  = !empty($_POST['send_printers']);
$send_phones    = !empty($_POST['send_phones']);

// If none selected, fall back to all (backwards compatibility with direct calls)
if (!$send_computers && !$send_printers && !$send_phones) {
   $send_computers = $send_printers = $send_phones = true;
}

$pdfs = PluginResponsivasGenerator::generateSelected($user_id, [
   'computers' => $send_computers,
   'printers'  => $send_printers,
   'phones'    => $send_phones,
]);

if (empty($pdfs)) {
   Session::addMessageAfterRedirect(
      __('There are no responsibility documents to generate for this user.', 'responsivas'),
      false,
      WARNING
   );
   $redirect_user($user_id);
   exit;
}

/* ============================
 * Construir cuerpo HTML del correo
 * ============================ */
// responsivasApplyTemplate: ** * __ en orden correcto, luego sustituye variables
$body_safe   = nl2br(responsivasApplyTemplate(htmlspecialchars($email_body_tpl,    ENT_QUOTES, 'UTF-8'), $vars));
$footer_raw  = responsivasApplyTemplate(htmlspecialchars($email_footer_tpl, ENT_QUOTES, 'UTF-8'), $vars);
$footer_safe = !empty(trim($email_footer_tpl))
   ? '<hr style="margin-top:24px;border:none;border-top:1px solid #ddd;">'
     . '<p style="color:#888;font-size:11px;">' . nl2br($footer_raw) . '</p>'
   : '';

$count           = count($pdfs);
$attachment_note = sprintf(
   _n('%d responsibility PDF attached.', '%d responsibility PDFs attached.', $count, 'responsivas'),
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

   global $CFG_GLPI;
   $fromName  = trim($CFG_GLPI['from_email_name']  ?? $CFG_GLPI['admin_email_name'] ?? '');
   $fromEmail = trim($CFG_GLPI['from_email']        ?? $CFG_GLPI['admin_email']      ?? '');
   if ($fromEmail !== '' && $fromName !== '') {
      $email->from(new \Symfony\Component\Mime\Address($fromEmail, $fromName));
   }
   $email->subject($email_subject);
   $email->to($user_email);
   $email->html($body_html);

   foreach ($pdfs as $pdf_item) {
      $email->attach($pdf_item['content'], $pdf_item['filename'], 'application/pdf');
   }

   $result = $mailer->send();

   if ($result) {
      Session::addMessageAfterRedirect(
         sprintf(__('Email successfully sent to %s.', 'responsivas'), $user_email),
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
         [0, '', sprintf(__('Responsibility documents sent by email to %s by %s (%d PDF).', 'responsivas'), $user_email, $quien, $count)],
         0,
         Log::HISTORY_LOG_SIMPLE_MESSAGE
      );

   } else {
      Session::addMessageAfterRedirect(
         sprintf(__('Error sending email: %s', 'responsivas'), __('The mail server rejected the sending.')),
         false,
         ERROR
      );
   }

} catch (Throwable $e) {
   Session::addMessageAfterRedirect(
      sprintf(__('Error sending email: %s', 'responsivas'), $e->getMessage()),
      false,
      ERROR
   );
}

/* ============================
 * Redirigir al perfil del usuario
 * ============================ */
$redirect_user($user_id);
exit;
