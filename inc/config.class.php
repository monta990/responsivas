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


function plugin_responsivas_get_latest_release_version(): ?string {
    $cacheDir = PluginResponsivasPaths::filesDir();
    $cache    = $cacheDir . '/release_cache.json';
    $ttl      = 21600;
    $staleVersion = null;

    if (is_file($cache) && is_readable($cache)) {
        $cached = json_decode((string) @file_get_contents($cache), true);
        if (is_array($cached) && !empty($cached['version'])) {
            $candidate = (string) $cached['version'];
            if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $candidate)) {
                $staleVersion = $candidate;
                if (!empty($cached['checked_at']) && (time() - (int) $cached['checked_at']) < $ttl) {
                    return $candidate;
                }
            }
        }
    }

    $url = 'https://api.github.com/repos/monta990/responsivas/releases/latest';
    $context = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "User-Agent: GLPI-Responsivas/1.4.9\r\nAccept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
            'timeout'       => 4,
            'ignore_errors' => true,
        ],
    ]);

    // Limitar la respuesta a 64 KiB: el endpoint latest no necesita más.
    $json = @file_get_contents($url, false, $context, 0, 65536);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
            $status = (int) $m[1];
        }
    }
    if ($json === false || $status !== 200) {
        return $staleVersion;
    }

    $data = json_decode($json, true);
    if (!is_array($data)
        || empty($data['tag_name'])
        || !empty($data['draft'])
        || !empty($data['prerelease'])) {
        return $staleVersion;
    }

    $version = preg_replace('/^[vV]/', '', trim((string) $data['tag_name']));
    if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        return $staleVersion;
    }

    if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0750, true) && !is_dir($cacheDir)) {
        return $version;
    }

    $payload = json_encode([
        'checked_at' => time(),
        'version'    => $version,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (is_string($payload)) {
        $tmp = $cache . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @chmod($tmp, 0640);
            if (!@rename($tmp, $cache)) {
                @unlink($tmp);
            }
        }
    }

    return $version;
}

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
$latestReleaseVersion = plugin_responsivas_get_latest_release_version();
$currentPluginVersion  = plugin_version_responsivas()['version'] ?? '1.4.9';
$updateAvailable       = $latestReleaseVersion !== null && version_compare($latestReleaseVersion, $currentPluginVersion, '>');

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
            $imageWritten = false;
            if ($mime === 'image/jpeg') {
                $img = @imagecreatefromjpeg($tmpFile);
                $errorMessage = __('Error processing JPG image', 'responsivas');
            } else {
                $img = @imagecreatefrompng($tmpFile);
                $errorMessage = __('Error processing PNG image', 'responsivas');
            }

            // Re-codificar siempre la imagen: nunca persistir directamente bytes subidos.
            if ($img !== false && @imagepng($img, $logoPath)) {
                imagedestroy($img);
                $imageWritten = true;
            } else {
                if ($img !== false) {
                    imagedestroy($img);
                }
                Session::addMessageAfterRedirect($errorMessage, false, ERROR);
            }
            if ($imageWritten) {
                chmod($logoPath, 0644);
                Session::addMessageAfterRedirect(
                    __('Logo updated successfully.', 'responsivas'), false, INFO
                );
                $logo_uploaded = true;
            }
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
    'currentPluginVersion' => $currentPluginVersion,
    'latestReleaseVersion' => $latestReleaseVersion,
    'updateAvailable'      => $updateAvailable,
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
