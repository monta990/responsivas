<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

require_once dirname(__DIR__) . '/inc/paths.class.php';
require_once dirname(__DIR__) . '/inc/helpers.php';
Session::checkRight('config', UPDATE);

$self   = PluginResponsivasPaths::webDir() . '/front/config.form.php';
$config = Config::getConfigurationValues('plugin_responsivas');

function dropdownUser(string $name, array $config): void {
    User::dropdown([
        'name'   => $name,
        'value'  => $config[$name] ?? 0,
        'right'  => 'all',
        'active' => 1,
    ]);
}

/* =====================================================
 * Runtime constants
 * ===================================================== */
$maxSize     = 500 * 1024;
$allowedMime = ['image/png', 'image/jpeg'];
$logoPath    = PluginResponsivasPaths::logoPath();

/* =====================================================
 * POST: delete logo
 * ===================================================== */
if (isset($_POST['delete_logo'])) {

    if (!Session::haveRight('config', UPDATE)) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }

    if (is_file($logoPath) && !@unlink($logoPath)) {
        Session::addMessageAfterRedirect(
            __('Could not delete the logo.', 'responsivas'),
            false,
            ERROR
        );
        Html::redirect($self);
        return;
    }

    Session::addMessageAfterRedirect(
        __('Logo deleted successfully.', 'responsivas'),
        false,
        INFO
    );
    Html::redirect($self);
}

/* =====================================================
 * POST: save configuration
 * ===================================================== */
if (isset($_POST['update'])) {

    $values = [
        'timezone'             => (function () {
            $tz = cleanerEscape($_POST['timezone'] ?? '');
            return in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : 'America/Hermosillo';
        })(),
        'show_employee_number' => isset($_POST['show_employee_number']),
        'show_qr'              => isset($_POST['show_qr']),
        'pdf_compression'      => isset($_POST['pdf_compression'])      ? 1 : 0,
        'pdf_protection'       => isset($_POST['pdf_protection'])       ? 1 : 0,
        'watermark_text'       => mb_substr(cleanerEscape(trim($_POST['watermark_text'] ?? '')), 0, 40),
        'watermark_opacity'    => max(5, min(100, (int)($_POST['watermark_opacity'] ?? 25))),
        'company_name'         => cleanerEscape(trim($_POST['company_name'] ?? '')),
        'currency'             => cleanerEscape(trim($_POST['currency']      ?? '$')),
        'testigo_1'            => (int)($_POST['testigo_1']       ?? 0),
        'testigo_2'            => (int)($_POST['testigo_2']       ?? 0),
        'representante'        => (int)($_POST['representante']   ?? 0),
        'cellphone_type_id'    => (int)($_POST['cellphone_type_id'] ?? 0),

        'pc_font_size'          => max(6, min(72, (int)($_POST['pc_font_size']  ?? 10))),
        'pc_titulo'             => cleanerEscape(trim($_POST['pc_titulo']  ?? '')),
        'pc_intro'              => trim($_POST['pc_intro']   ?? ''),
        'pc_cuerpo'             => trim($_POST['pc_cuerpo']  ?? ''),
        'pc_show_comodato_sigs' => isset($_POST['pc_show_comodato_sigs']) ? 1 : 0,

        'pri_font_size'          => max(6, min(72, (int)($_POST['pri_font_size'] ?? 10))),
        'pri_titulo'             => cleanerEscape(trim($_POST['pri_titulo']  ?? '')),
        'pri_intro'              => trim($_POST['pri_intro']   ?? ''),
        'pri_cuerpo'             => trim($_POST['pri_cuerpo']  ?? ''),
        'pri_show_comodato_sigs' => isset($_POST['pri_show_comodato_sigs']) ? 1 : 0,

        'pho_font_size'           => max(6, min(72, (int)($_POST['pho_font_size'] ?? 9))),
        'pho_titulo'              => cleanerEscape(trim($_POST['pho_titulo']           ?? '')),
        'pho_apertura'            => trim($_POST['pho_apertura']          ?? ''),
        'pho_clausulas'           => trim($_POST['pho_clausulas']         ?? ''),
        'pho_testigos'            => trim($_POST['pho_testigos']          ?? ''),
        'pho_vida_util_factura'   => trim($_POST['pho_vida_util_factura'] ?? ''),
        'pho_vida_util_sin'       => trim($_POST['pho_vida_util_sin']     ?? ''),

        'email_subject' => cleanerEscape(trim($_POST['email_subject'] ?? '')),
        'email_body'    => trim($_POST['email_body']   ?? ''),
        'email_footer'  => trim($_POST['email_footer'] ?? ''),
    ];

    foreach (['pc', 'pri', 'pho'] as $type) {
        foreach (['left_1', 'right_1', 'left_2', 'right_2'] as $pos) {
            $key = "{$type}_footer_{$pos}";
            $values[$key] = cleanerEscape($_POST[$key] ?? '');
        }
    }

    if ($values['cellphone_type_id'] === 0) {
        Session::addMessageAfterRedirect(
            __('You must select a valid phone type.', 'responsivas'),
            false,
            WARNING
        );
        Html::redirect($self);
        return;
    }

    Config::setConfigurationValues('plugin_responsivas', $values);
    $logo_uploaded = false;

    if (isset($_FILES['logo']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {

        $tmpFile = $_FILES['logo']['tmp_name'];
        $size    = $_FILES['logo']['size'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($tmpFile);

        if ($size > $maxSize) {
            Session::addMessageAfterRedirect(
                __('The file exceeds the maximum allowed size (500 KB).', 'responsivas'),
                false, ERROR
            );
        } elseif (!in_array($mime, $allowedMime)) {
            Session::addMessageAfterRedirect(
                __('Format not allowed. PNG or JPG only.', 'responsivas'),
                false, ERROR
            );
        } else {
            if (!is_dir(PluginResponsivasPaths::filesDir())) {
                mkdir(PluginResponsivasPaths::filesDir(), 0755, true);
            }
            if ($mime === 'image/jpeg') {
                $img = @imagecreatefromjpeg($tmpFile);
                if ($img !== false) {
                    imagepng($img, $logoPath);
                    unset($img);
                } else {
                    Session::addMessageAfterRedirect(
                        __('Error processing JPG image', 'responsivas'), false, ERROR
                    );
                }
            } else {
                $image_info = @getimagesize($_FILES['logo']['tmp_name']);
                if ($image_info === false) {
                    Session::addMessageAfterRedirect(
                        __('The uploaded file is not a valid image.', 'responsivas'), true, ERROR
                    );
                    return false;
                }
                if (!in_array($image_info['mime'], $allowedMime, true)) {
                    Session::addMessageAfterRedirect(
                        __('Unsupported image format.', 'responsivas'), true, ERROR
                    );
                    return false;
                }
                move_uploaded_file($tmpFile, $logoPath);
            }
            chmod($logoPath, 0644);
            Session::addMessageAfterRedirect(
                __('Logo updated successfully.', 'responsivas'), false, INFO
            );
            $logo_uploaded = true;
        }
    }

    if (!$logo_uploaded) {
        Session::addMessageAfterRedirect(
            __('Configuration saved successfully.', 'responsivas'), false, INFO
        );
    }

    Html::redirect($self);
}

/* =====================================================
 * Render page
 * ===================================================== */
Html::header(
    __('Responsivas', 'responsivas'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugins',
    'responsivas'
);

$hasLogo    = is_readable($logoPath);
$logoSizeKB = $logoWidth = $logoHeight = 0;
if ($hasLogo) {
    $logoSizeKB = round(filesize($logoPath) / 1024, 2);
    [$logoWidth, $logoHeight] = getimagesize($logoPath);
}

ob_start(); dropdownUser('testigo_1',    $config); $widget_testigo1      = ob_get_clean();
ob_start(); dropdownUser('testigo_2',    $config); $widget_testigo2      = ob_get_clean();
ob_start(); dropdownUser('representante',$config); $widget_representante = ob_get_clean();

ob_start();
Dropdown::show('PhoneType', [
    'name'  => 'cellphone_type_id',
    'value' => (int)($config['cellphone_type_id'] ?? 0),
    'width' => '100%',
]);
$widget_phone_type = ob_get_clean();

$nv = static function (string $v): string {
    $v = str_replace('\\n', "\n", $v);
    $v = preg_replace('/<strong>(.*?)<\/strong>/i',                                '**$1**', $v);
    $v = preg_replace('/<em>(.*?)<\/em>/i',                                         '*$1*',  $v);
    $v = preg_replace('/<u>(.*?)<\/u>/i',                                           '__$1__',$v);
    $v = preg_replace('/<span style=["\']font-weight:bold["\']>(.*?)<\/span>/i',   '**$1**', $v);
    $v = preg_replace('/<span style=["\']font-style:italic["\']>(.*?)<\/span>/i',   '*$1*',  $v);
    $v = preg_replace('/<span style=["\']text-decoration:underline["\']>(.*?)<\/span>/i', '__$1__', $v);
    return strip_tags($v);
};

$core_cfg         = Config::getConfigurationValues('core');
$mail_ok          = ($core_cfg['use_notifications'] ?? 0) == 1 && ($core_cfg['notifications_mailing'] ?? 0) == 1;
$has_email_config = !empty(trim($config['email_subject'] ?? '')) && !empty(trim($config['email_body'] ?? ''));
$web              = PluginResponsivasPaths::webDir();

echo PluginResponsivasTwig::env()->render('config/page.html.twig', [
    'self'                 => $self,
    'config'               => $config,
    'has_logo'             => $hasLogo,
    'logo_url'             => PluginResponsivasPaths::logoUrl(),
    'logo_url_cached'      => PluginResponsivasPaths::logoUrl() . '&t=' . time(),
    'logo_width'           => $logoWidth,
    'logo_height'          => $logoHeight,
    'logo_size_kb'         => $logoSizeKB,
    'timezone_list'        => DateTimeZone::listIdentifiers(),
    'pdf_font'             => Config::getConfigurationValue('core', 'pdffont'),
    'mail_ok'              => $mail_ok,
    'has_email_config'     => $has_email_config,
    'csrf_token'           => Session::getNewCSRFToken(),
    'test_action'          => $web . '/front/send_mail.php',
    'test_csrf_token'      => Session::getNewCSRFToken(),
    'widget_testigo1'      => $widget_testigo1,
    'widget_testigo2'      => $widget_testigo2,
    'widget_representante' => $widget_representante,
    'widget_phone_type'    => $widget_phone_type,
    'preview_pc'           => $web . '/front/preview.php?type=pc',
    'preview_pri'          => $web . '/front/preview.php?type=pri',
    'preview_pho'          => $web . '/front/preview.php?type=pho',
    'pc_titulo_v'             => $nv($config['pc_titulo']            ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO'),
    'pc_intro_v'              => $nv($config['pc_intro']             ?? ''),
    'pc_cuerpo_v'             => $nv($config['pc_cuerpo']            ?? ''),
    'pri_titulo_v'            => $nv($config['pri_titulo']           ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO'),
    'pri_intro_v'             => $nv($config['pri_intro']            ?? ''),
    'pri_cuerpo_v'            => $nv($config['pri_cuerpo']           ?? ''),
    'pho_titulo_v'            => $nv($config['pho_titulo']           ?? 'CONTRATO DE COMODATO'),
    'pho_apertura_v'          => $nv($config['pho_apertura']         ?? ''),
    'pho_clausulas_v'         => $nv($config['pho_clausulas']        ?? ''),
    'pho_testigos_v'          => $nv($config['pho_testigos']         ?? ''),
    'pho_vida_util_factura_v' => $nv($config['pho_vida_util_factura'] ?? ''),
    'pho_vida_util_sin_v'     => $nv($config['pho_vida_util_sin']    ?? ''),
]);

Html::footer();
