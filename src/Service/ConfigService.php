<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Service;

use GlpiPlugin\Responsivas\Paths;
use GlpiPlugin\Responsivas\Twig;
use GlpiPlugin\Responsivas\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class ConfigService
{
    private static function exportableKeys(): array
    {
        return [
            'timezone', 'show_employee_number', 'show_qr', 'pdf_compression',
            'pdf_protection', 'watermark_text', 'watermark_opacity',
            'company_name', 'currency', 'testigo_1', 'testigo_2',
            'representante', 'cellphone_type_id',
            'pc_font_size', 'pc_titulo', 'pc_intro', 'pc_cuerpo',
            'pc_vida_util_factura', 'pc_vida_util_sin', 'pc_show_comodato_sigs',
            'pri_font_size', 'pri_titulo', 'pri_intro', 'pri_cuerpo',
            'pri_show_comodato_sigs',
            'pho_font_size', 'pho_titulo', 'pho_apertura', 'pho_clausulas',
            'pho_testigos', 'pho_vida_util_factura', 'pho_vida_util_sin',
            'email_subject', 'email_body', 'email_footer',
            'pc_footer_left_1', 'pc_footer_right_1', 'pc_footer_left_2', 'pc_footer_right_2',
            'pri_footer_left_1', 'pri_footer_right_1', 'pri_footer_left_2', 'pri_footer_right_2',
            'pho_footer_left_1', 'pho_footer_right_1', 'pho_footer_left_2', 'pho_footer_right_2',            'pc_enable_visual_inspection', 'pc_enable_return_form',
            'pri_enable_visual_inspection', 'pri_enable_return_form',
            'pho_enable_visual_inspection', 'pho_enable_return_form',
            'pc_inspection_title', 'pc_inspection_instructions', 'pc_return_title', 'pc_return_instructions',
            'pri_inspection_title', 'pri_inspection_instructions', 'pri_return_title', 'pri_return_instructions',
            'pho_inspection_title', 'pho_inspection_instructions', 'pho_return_title', 'pho_return_instructions',
            'pc_inspection_footer_left_1', 'pc_inspection_footer_right_1', 'pc_inspection_footer_left_2', 'pc_inspection_footer_right_2', 'pc_return_footer_left_1', 'pc_return_footer_right_1', 'pc_return_footer_left_2', 'pc_return_footer_right_2', 'pri_inspection_footer_left_1', 'pri_inspection_footer_right_1', 'pri_inspection_footer_left_2', 'pri_inspection_footer_right_2', 'pri_return_footer_left_1', 'pri_return_footer_right_1', 'pri_return_footer_left_2', 'pri_return_footer_right_2', 'pho_inspection_footer_left_1', 'pho_inspection_footer_right_1', 'pho_inspection_footer_left_2', 'pho_inspection_footer_right_2', 'pho_return_footer_left_1', 'pho_return_footer_right_1', 'pho_return_footer_left_2', 'pho_return_footer_right_2',

        ];
    }

    public static function export(): Response
    {
        \Session::checkLoginUser();
        \Session::checkRight('config', UPDATE);
        $config = \Config::getConfigurationValues('plugin_responsivas');
        $data = [
            'format' => 'responsivas-config',
            'format_version' => 1,
            'plugin_version' => plugin_version_responsivas()['version'] ?? PLUGIN_RESPONSIVAS_VERSION,
            'exported_at' => gmdate('c'),
            'configuration' => [],
        ];

        foreach (self::exportableKeys() as $key) {
            if (array_key_exists($key, $config)) {
                $data['configuration'][$key] = $config[$key];
            }
        }

        $data['references'] = [];
        foreach (['testigo_1', 'testigo_2', 'representante'] as $key) {
            $id = (int)($config[$key] ?? 0);
            if ($id > 0) {
                $user = new \User();
                if ($user->getFromDB($id)) {
                    $data['references'][$key] = [
                        'id' => $id,
                        'name' => (string)($user->fields['name'] ?? ''),
                    ];
                }
            }
        }
        $phoneTypeId = (int)($config['cellphone_type_id'] ?? 0);
        if ($phoneTypeId > 0) {
            $phoneType = new \PhoneType();
            if ($phoneType->getFromDB($phoneTypeId)) {
                $data['references']['cellphone_type_id'] = [
                    'id' => $phoneTypeId,
                    'name' => (string)($phoneType->fields['name'] ?? ''),
                ];
            }
        }

        $logoPath = Paths::logoPath();
        if (is_file($logoPath) && is_readable($logoPath)) {
            $logo = file_get_contents($logoPath);
            if ($logo !== false && strlen($logo) <= (700 * 1024)) {
                $data['logo'] = [
                    'mime' => 'image/png',
                    'data' => base64_encode($logo),
                ];
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException(__('Could not create the configuration export.', 'responsivas'));
        }

        $filename = 'responsivas-config-' . date('Y-m-d_H-i-s') . '.json';
        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function importConfiguration(array $file): Response
    {
        $self = Paths::routeUrl('config');

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            \Session::addMessageAfterRedirect(__('Could not read the configuration file.', 'responsivas'), false, ERROR);
            return new RedirectResponse($self);
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > (2 * 1024 * 1024)) {
            \Session::addMessageAfterRedirect(__('The configuration file is invalid or exceeds the maximum size of 2 MB.', 'responsivas'), false, ERROR);
            return new RedirectResponse($self);
        }

        $raw = file_get_contents($file['tmp_name']);
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data)
            || ($data['format'] ?? '') !== 'responsivas-config'
            || (int)($data['format_version'] ?? 0) !== 1
            || !is_array($data['configuration'] ?? null)
        ) {
            \Session::addMessageAfterRedirect(__('The selected file is not a valid Responsivas configuration export.', 'responsivas'), false, ERROR);
            return new RedirectResponse($self);
        }

        $current = \Config::getConfigurationValues('plugin_responsivas');
        $allowed = array_flip(self::exportableKeys());
        $values = [];
        $skipped = [];

        foreach ($data['configuration'] as $key => $value) {
            if (!isset($allowed[$key])) continue;
            if (in_array($key, [
                'show_employee_number','show_qr','pdf_compression','pdf_protection',
                'pc_show_comodato_sigs','pri_show_comodato_sigs',
                'pc_enable_visual_inspection','pc_enable_return_form',
                'pri_enable_visual_inspection','pri_enable_return_form',
                'pho_enable_visual_inspection','pho_enable_return_form',
            ], true)) {
                $values[$key] = (bool)$value ? 1 : 0;
            } elseif (in_array($key, [
                'testigo_1','testigo_2','representante','cellphone_type_id',
                'pc_font_size','pri_font_size','pho_font_size','watermark_opacity',
            ], true)) {
                $values[$key] = (int)$value;
            } else {
                $values[$key] = is_scalar($value) ? (string)$value : '';
            }
        }

        global $DB;
        foreach (['testigo_1','testigo_2','representante'] as $key) {
            if (!empty($values[$key])) {
                $u = new \User();
                if (!$u->getFromDB((int)$values[$key])) {
                    $name = (string)($data['references'][$key]['name'] ?? '');
                    $resolved = 0;
                    if ($name !== '') {
                        $row = $DB->request([
                            'FROM' => 'glpi_users',
                            'WHERE' => ['name' => $name],
                            'LIMIT' => 1,
                        ])->current();
                        $resolved = (int)($row['id'] ?? 0);
                    }
                    if ($resolved > 0) {
                        $values[$key] = $resolved;
                    } else {
                        $values[$key] = (int)($current[$key] ?? 0);
                        $skipped[] = $key;
                    }
                }
            }
        }

        if (!empty($values['cellphone_type_id'])) {
            $phoneType = new \PhoneType();
            if (!$phoneType->getFromDB((int)$values['cellphone_type_id'])) {
                $name = (string)($data['references']['cellphone_type_id']['name'] ?? '');
                $resolved = 0;
                if ($name !== '') {
                    $row = $DB->request([
                        'FROM' => 'glpi_phonetypes',
                        'WHERE' => ['name' => $name],
                        'LIMIT' => 1,
                    ])->current();
                    $resolved = (int)($row['id'] ?? 0);
                }
                if ($resolved > 0) {
                    $values['cellphone_type_id'] = $resolved;
                } else {
                    $values['cellphone_type_id'] = (int)($current['cellphone_type_id'] ?? 0);
                    $skipped[] = 'cellphone_type_id';
                }
            }
        }

        $values['pc_font_size'] = max(6, min(72, (int)($values['pc_font_size'] ?? ($current['pc_font_size'] ?? 10))));
        $values['pri_font_size'] = max(6, min(72, (int)($values['pri_font_size'] ?? ($current['pri_font_size'] ?? 10))));
        $values['pho_font_size'] = max(6, min(72, (int)($values['pho_font_size'] ?? ($current['pho_font_size'] ?? 9))));
        $values['watermark_opacity'] = max(5, min(100, (int)($values['watermark_opacity'] ?? ($current['watermark_opacity'] ?? 25))));

        if (isset($values['timezone']) && !in_array($values['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $values['timezone'] = $current['timezone'] ?? 'America/Hermosillo';
            $skipped[] = 'timezone';
        }

        foreach (['company_name','currency','watermark_text','pc_titulo','email_subject'] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = mb_substr(trim($values[$key]), 0, $key === 'watermark_text' ? 40 : 1000);
            }
        }
        foreach ([
            'pc_intro','pc_cuerpo','pc_vida_util_factura','pc_vida_util_sin',
            'pri_intro','pri_cuerpo','pho_apertura','pho_clausulas','pho_testigos',
            'pho_vida_util_factura','pho_vida_util_sin','email_body','email_footer',
        ] as $key) {
            if (array_key_exists($key, $values)) $values[$key] = trim($values[$key]);
        }
        foreach (['pc','pri','pho'] as $type) {
            foreach (['left_1','right_1','left_2','right_2'] as $pos) {
                $key = "{$type}_footer_{$pos}";
                if (array_key_exists($key, $values)) $values[$key] = trim($values[$key]);
            }
        }

        \Config::setConfigurationValues('plugin_responsivas', $values);

        $logoImported = false;
        if (is_array($data['logo'] ?? null) && isset($data['logo']['data'])) {
            $mime = (string)($data['logo']['mime'] ?? '');
            $decoded = base64_decode((string)$data['logo']['data'], true);
            if ($decoded !== false && $mime === 'image/png' && strlen($decoded) <= (500 * 1024)) {
                if (!is_dir(Paths::filesDir())) @mkdir(Paths::filesDir(), 0755, true);
                $tmp = tempnam(Paths::filesDir(), 'responsivas_import_');
                if ($tmp !== false) {
                    $img = @imagecreatefromstring($decoded);
                    if ($img !== false) {
                        imagealphablending($img, false);
                        imagesavealpha($img, true);
                    }
                    if ($img !== false && @imagepng($img, $tmp)) {
                        imagedestroy($img);
                        if (@rename($tmp, Paths::logoPath())) {
                            @chmod(Paths::logoPath(), 0644);
                            $logoImported = true;
                        } else {
                            @unlink($tmp);
                        }
                    } else {
                        if ($img !== false) imagedestroy($img);
                        @unlink($tmp);
                    }
                }
            } else {
                $skipped[] = 'logo';
            }
        }

        $message = __('Configuration imported successfully.', 'responsivas');
        if ($logoImported) $message .= ' ' . __('Logo imported successfully.', 'responsivas');
        if ($skipped !== []) {
            $message .= ' ' . sprintf(
                __('Some values were skipped because they are not valid in this GLPI installation: %s.', 'responsivas'),
                implode(', ', array_unique($skipped))
            );
        }

        \Session::addMessageAfterRedirect($message, false, INFO);
        return new RedirectResponse($self);
    }

    private static function dropdownUser(string $name, array $config): void
    {
        \User::dropdown([
            'name' => $name,
            'value' => $config[$name] ?? 0,
            'right' => 'all',
            'active' => 1,
        ]);
    }

    public static function handle(): Response
    {
        \Session::checkLoginUser();
        \Session::checkRight('config', UPDATE);

        $self = Paths::routeUrl('config');
        $config = \Config::getConfigurationValues('plugin_responsivas');

/* =====================================================
 * Runtime constants
 * ===================================================== */
$maxSize     = 500 * 1024;
$allowedMime = ['image/png', 'image/jpeg'];
$logoPath    = Paths::logoPath();
$latestReleaseVersion = UpdateChecker::latest();
$currentPluginVersion  = plugin_version_responsivas()['version'] ?? '1.6.0';
$updateAvailable       = $latestReleaseVersion !== null && version_compare($latestReleaseVersion, $currentPluginVersion, '>');

/* =====================================================
 * POST: export/import configuration
 * ===================================================== */
if (isset($_POST['import_config'])) {
    return self::importConfiguration($_FILES['config_file'] ?? []);
}

/* =====================================================
 * POST: delete logo
 * ===================================================== */
if (isset($_POST['delete_logo'])) {

    if (!\Session::haveRight('config', UPDATE)) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }

    if (is_file($logoPath) && !@unlink($logoPath)) {
        \Session::addMessageAfterRedirect(
            __('Could not delete the logo.', 'responsivas'),
            false,
            ERROR
        );
        return new \Symfony\Component\HttpFoundation\RedirectResponse($self);
    }

    \Session::addMessageAfterRedirect(
        __('Logo deleted successfully.', 'responsivas'),
        false,
        INFO
    );
    return new \Symfony\Component\HttpFoundation\RedirectResponse($self);
}

/* =====================================================
 * POST: save configuration
 * ===================================================== */
if (isset($_POST['update'])) {

    $values = [
        'timezone'             => (function () {
            $tz = Utils::escape($_POST['timezone'] ?? '');
            return in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : 'America/Hermosillo';
        })(),
        'show_employee_number' => isset($_POST['show_employee_number']),
        'show_qr'              => isset($_POST['show_qr']),
        'pdf_compression'      => isset($_POST['pdf_compression'])      ? 1 : 0,
        'pdf_protection'       => isset($_POST['pdf_protection'])       ? 1 : 0,
        'watermark_text'       => mb_substr(Utils::escape(trim($_POST['watermark_text'] ?? '')), 0, 40),
        'watermark_opacity'    => max(5, min(100, (int)($_POST['watermark_opacity'] ?? 25))),
        'company_name'         => Utils::escape(trim($_POST['company_name'] ?? '')),
        'currency'             => Utils::escape(trim($_POST['currency']      ?? '$')),
        'testigo_1'            => (int)($_POST['testigo_1']       ?? 0),
        'testigo_2'            => (int)($_POST['testigo_2']       ?? 0),
        'representante'        => (int)($_POST['representante']   ?? 0),
        'cellphone_type_id'    => (int)($_POST['cellphone_type_id'] ?? 0),

        'pc_font_size'          => max(6, min(72, (int)($_POST['pc_font_size']  ?? 10))),
        'pc_titulo'             => Utils::escape(trim($_POST['pc_titulo']  ?? '')),
        'pc_intro'              => trim($_POST['pc_intro']   ?? ''),
        'pc_cuerpo'             => trim($_POST['pc_cuerpo']  ?? ''),
        'pc_vida_util_factura'  => trim($_POST['pc_vida_util_factura'] ?? ''),
        'pc_vida_util_sin'      => trim($_POST['pc_vida_util_sin'] ?? ''),
        'pc_show_comodato_sigs' => isset($_POST['pc_show_comodato_sigs']) ? 1 : 0,

        'pri_font_size'          => max(6, min(72, (int)($_POST['pri_font_size'] ?? 10))),
        'pri_titulo'             => Utils::escape(trim($_POST['pri_titulo']  ?? '')),
        'pri_intro'              => trim($_POST['pri_intro']   ?? ''),
        'pri_cuerpo'             => trim($_POST['pri_cuerpo']  ?? ''),
        'pri_show_comodato_sigs' => isset($_POST['pri_show_comodato_sigs']) ? 1 : 0,

        'pho_font_size'           => max(6, min(72, (int)($_POST['pho_font_size'] ?? 9))),
        'pho_titulo'              => Utils::escape(trim($_POST['pho_titulo']           ?? '')),
        'pho_apertura'            => trim($_POST['pho_apertura']          ?? ''),
        'pho_clausulas'           => trim($_POST['pho_clausulas']         ?? ''),
        'pho_testigos'            => trim($_POST['pho_testigos']          ?? ''),
        'pho_vida_util_factura'   => trim($_POST['pho_vida_util_factura'] ?? ''),
        'pho_vida_util_sin'       => trim($_POST['pho_vida_util_sin']     ?? ''),
        'pc_enable_visual_inspection'  => isset($_POST['pc_enable_visual_inspection']) ? 1 : 0,
        'pc_enable_return_form'        => isset($_POST['pc_enable_return_form']) ? 1 : 0,
        'pri_enable_visual_inspection' => isset($_POST['pri_enable_visual_inspection']) ? 1 : 0,
        'pri_enable_return_form'       => isset($_POST['pri_enable_return_form']) ? 1 : 0,
        'pho_enable_visual_inspection' => isset($_POST['pho_enable_visual_inspection']) ? 1 : 0,
        'pho_enable_return_form'       => isset($_POST['pho_enable_return_form']) ? 1 : 0,
        'pc_inspection_title'        => Utils::escape(trim($_POST['pc_inspection_title'] ?? '')),
        'pc_inspection_instructions' => trim($_POST['pc_inspection_instructions'] ?? ''),
        'pc_return_title'            => Utils::escape(trim($_POST['pc_return_title'] ?? '')),
        'pc_return_instructions'     => trim($_POST['pc_return_instructions'] ?? ''),
        'pri_inspection_title'        => Utils::escape(trim($_POST['pri_inspection_title'] ?? '')),
        'pri_inspection_instructions' => trim($_POST['pri_inspection_instructions'] ?? ''),
        'pri_return_title'            => Utils::escape(trim($_POST['pri_return_title'] ?? '')),
        'pri_return_instructions'     => trim($_POST['pri_return_instructions'] ?? ''),
        'pho_inspection_title'        => Utils::escape(trim($_POST['pho_inspection_title'] ?? '')),
        'pho_inspection_instructions' => trim($_POST['pho_inspection_instructions'] ?? ''),
        'pho_return_title'            => Utils::escape(trim($_POST['pho_return_title'] ?? '')),
        'pho_return_instructions'     => trim($_POST['pho_return_instructions'] ?? ''),
        'pc_inspection_footer_left_1' => trim($_POST['pc_inspection_footer_left_1'] ?? ''),
        'pc_inspection_footer_right_1' => trim($_POST['pc_inspection_footer_right_1'] ?? ''),
        'pc_inspection_footer_left_2' => trim($_POST['pc_inspection_footer_left_2'] ?? ''),
        'pc_inspection_footer_right_2' => trim($_POST['pc_inspection_footer_right_2'] ?? ''),
        'pc_return_footer_left_1' => trim($_POST['pc_return_footer_left_1'] ?? ''),
        'pc_return_footer_right_1' => trim($_POST['pc_return_footer_right_1'] ?? ''),
        'pc_return_footer_left_2' => trim($_POST['pc_return_footer_left_2'] ?? ''),
        'pc_return_footer_right_2' => trim($_POST['pc_return_footer_right_2'] ?? ''),
        'pri_inspection_footer_left_1' => trim($_POST['pri_inspection_footer_left_1'] ?? ''),
        'pri_inspection_footer_right_1' => trim($_POST['pri_inspection_footer_right_1'] ?? ''),
        'pri_inspection_footer_left_2' => trim($_POST['pri_inspection_footer_left_2'] ?? ''),
        'pri_inspection_footer_right_2' => trim($_POST['pri_inspection_footer_right_2'] ?? ''),
        'pri_return_footer_left_1' => trim($_POST['pri_return_footer_left_1'] ?? ''),
        'pri_return_footer_right_1' => trim($_POST['pri_return_footer_right_1'] ?? ''),
        'pri_return_footer_left_2' => trim($_POST['pri_return_footer_left_2'] ?? ''),
        'pri_return_footer_right_2' => trim($_POST['pri_return_footer_right_2'] ?? ''),
        'pho_inspection_footer_left_1' => trim($_POST['pho_inspection_footer_left_1'] ?? ''),
        'pho_inspection_footer_right_1' => trim($_POST['pho_inspection_footer_right_1'] ?? ''),
        'pho_inspection_footer_left_2' => trim($_POST['pho_inspection_footer_left_2'] ?? ''),
        'pho_inspection_footer_right_2' => trim($_POST['pho_inspection_footer_right_2'] ?? ''),
        'pho_return_footer_left_1' => trim($_POST['pho_return_footer_left_1'] ?? ''),
        'pho_return_footer_right_1' => trim($_POST['pho_return_footer_right_1'] ?? ''),
        'pho_return_footer_left_2' => trim($_POST['pho_return_footer_left_2'] ?? ''),
        'pho_return_footer_right_2' => trim($_POST['pho_return_footer_right_2'] ?? ''),



        'email_subject' => Utils::escape(trim($_POST['email_subject'] ?? '')),
        'email_body'    => trim($_POST['email_body']   ?? ''),
        'email_footer'  => trim($_POST['email_footer'] ?? ''),
    ];

    foreach (['pc', 'pri', 'pho'] as $type) {
        foreach (['left_1', 'right_1', 'left_2', 'right_2'] as $pos) {
            $key = "{$type}_footer_{$pos}";
            $values[$key] = Utils::escape($_POST[$key] ?? '');
        }
    }

    if ($values['cellphone_type_id'] === 0) {
        \Session::addMessageAfterRedirect(
            __('You must select a valid phone type.', 'responsivas'),
            false,
            WARNING
        );
        return new \Symfony\Component\HttpFoundation\RedirectResponse($self);
    }

    \Config::setConfigurationValues('plugin_responsivas', $values);
    $logo_uploaded = false;

    if (isset($_FILES['logo']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {

        $tmpFile = $_FILES['logo']['tmp_name'];
        $size    = $_FILES['logo']['size'];
        $finfo   = new \finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($tmpFile);

        if ($size > $maxSize) {
            \Session::addMessageAfterRedirect(
                __('The file exceeds the maximum allowed size (500 KB).', 'responsivas'),
                false, ERROR
            );
        } elseif (!in_array($mime, $allowedMime, true)) {
            \Session::addMessageAfterRedirect(
                __('Format not allowed. PNG or JPG only.', 'responsivas'),
                false, ERROR
            );
        } else {
            $imageInfo = @getimagesize($tmpFile);
            $maxDimension = 4096;
            $maxPixels = 12_000_000;
            $validDimensions = is_array($imageInfo)
                && isset($imageInfo[0], $imageInfo[1])
                && (int)$imageInfo[0] <= $maxDimension
                && (int)$imageInfo[1] <= $maxDimension
                && ((int)$imageInfo[0] * (int)$imageInfo[1]) <= $maxPixels;

            if (!$validDimensions) {
                \Session::addMessageAfterRedirect(
                    __('The image dimensions exceed the allowed limit (4096 × 4096 pixels and 12 megapixels).', 'responsivas'),
                    false, ERROR
                );
            } else {
            if (!is_dir(Paths::filesDir())) {
                mkdir(Paths::filesDir(), 0755, true);
            }
            $imageWritten = false;
            if ($mime === 'image/jpeg') {
                $img = @imagecreatefromjpeg($tmpFile);
                $errorMessage = __('Error processing JPG image', 'responsivas');
            } else {
                $img = @imagecreatefrompng($tmpFile);
                $errorMessage = __('Error processing PNG image', 'responsivas');
                // Preserve the alpha channel from transparent PNG logos.
                if ($img !== false) {
                    imagealphablending($img, false);
                    imagesavealpha($img, true);
                }
            }

            // Re-codificar siempre la imagen: nunca persistir directamente bytes subidos.
            if ($img !== false && @imagepng($img, $logoPath)) {
                imagedestroy($img);
                $imageWritten = true;
            } else {
                if ($img !== false) {
                    imagedestroy($img);
                }
                \Session::addMessageAfterRedirect($errorMessage, false, ERROR);
            }
            if ($imageWritten) {
                chmod($logoPath, 0644);
                \Session::addMessageAfterRedirect(
                    __('Logo updated successfully.', 'responsivas'), false, INFO
                );
                $logo_uploaded = true;
            }
            }
        }
    }

    if (!$logo_uploaded) {
        \Session::addMessageAfterRedirect(
            __('Configuration saved successfully.', 'responsivas'), false, INFO
        );
    }

    return new \Symfony\Component\HttpFoundation\RedirectResponse($self);
}

/* =====================================================
 * Render page
 * ===================================================== */
ob_start();
\Html::header(
    __('Responsivas', 'responsivas'),
    Paths::routeUrl('config'),
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

ob_start(); self::dropdownUser('testigo_1',    $config); $widget_testigo1      = ob_get_clean();
ob_start(); self::dropdownUser('testigo_2',    $config); $widget_testigo2      = ob_get_clean();
ob_start(); self::dropdownUser('representante',$config); $widget_representante = ob_get_clean();

ob_start();
\Dropdown::show('PhoneType', [
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

$core_cfg         = \Config::getConfigurationValues('core');
$mail_ok          = ($core_cfg['use_notifications'] ?? 0) == 1 && ($core_cfg['notifications_mailing'] ?? 0) == 1;
$has_email_config = !empty(trim($config['email_subject'] ?? '')) && !empty(trim($config['email_body'] ?? ''));
$web              = Paths::webDir();

echo Twig::env()->render('config/page.html.twig', [
    'self'                 => $self,
    'export_action'        => Paths::routeUrl('config/export'),
    'config'               => $config,
    'currentPluginVersion' => $currentPluginVersion,
    'latestReleaseVersion' => $latestReleaseVersion,
    'updateAvailable'      => $updateAvailable,
    'has_logo'             => $hasLogo,
    'logo_url'             => Paths::logoUrl(),
    'logo_url_cached'      => Paths::logoUrl() . '?t=' . time(),
    'logo_width'           => $logoWidth,
    'logo_height'          => $logoHeight,
    'logo_size_kb'         => $logoSizeKB,
    'timezone_list'        => \DateTimeZone::listIdentifiers(),
    'pdf_font'             => \Config::getConfigurationValue('core', 'pdffont'),
    'mail_ok'              => $mail_ok,
    'has_email_config'     => $has_email_config,
    'csrf_token'           => \Session::getNewCSRFToken(),
    'test_action'          => Paths::routeUrl('mail'),
    'test_csrf_token'      => \Session::getNewCSRFToken(),
    'widget_testigo1'      => $widget_testigo1,
    'widget_testigo2'      => $widget_testigo2,
    'widget_representante' => $widget_representante,
    'widget_phone_type'    => $widget_phone_type,
    'preview_pc'           => Paths::routeUrl('preview?type=pc'),
    'preview_pc_inspection'       => Paths::routeUrl('preview?type=manual_pc_inspection'),
    'preview_pc_return'           => Paths::routeUrl('preview?type=manual_pc_return'),
    'preview_pri_inspection'      => Paths::routeUrl('preview?type=manual_pri_inspection'),
    'preview_pri_return'          => Paths::routeUrl('preview?type=manual_pri_return'),
    'preview_pho_inspection'      => Paths::routeUrl('preview?type=manual_pho_inspection'),
    'preview_pho_return'          => Paths::routeUrl('preview?type=manual_pho_return'),

    'preview_pri'          => Paths::routeUrl('preview?type=pri'),
    'preview_pho'          => Paths::routeUrl('preview?type=pho'),
    'pc_titulo_v'             => $nv($config['pc_titulo']            ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO'),
    'pc_intro_v'              => $nv($config['pc_intro']             ?? ''),
    'pc_cuerpo_v'             => $nv($config['pc_cuerpo']            ?? ''),
    'pc_vida_util_factura_v'  => $nv($config['pc_vida_util_factura'] ?? ''),
    'pc_vida_util_sin_v'      => $nv($config['pc_vida_util_sin']     ?? ''),
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

\Html::footer();
        return new \Symfony\Component\HttpFoundation\Response(ob_get_clean());

    }
}
