<?php

if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

require_once dirname(__DIR__) . '/inc/paths.class.php';
require_once dirname(__DIR__) . '/inc/helpers.php';
Session::checkRight('config', UPDATE);

$self = Plugin::getWebDir('responsivas') . '/front/config.form.php';
$config = Config::getConfigurationValues('plugin_responsivas');

/**
 * Renderiza un sub-header con ribbon para cada sección de configuración.
 *
 * @param string $icon  Clase de icono de Tabler (ej: 'ti-device-desktop')
 * @param string $title Clave de traducción o texto del título
 */
function responsivasRibbonSubHeader(string $icon, string $title): void {
    echo "<div class='card-header mb-1 py-1 position-relative'>";
    echo "<div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
            <i class='fs-2x ti {$icon}' aria-hidden='true'></i>
          </div>";
    echo "<h3 class='card-subtitle ms-5 mb-0'>" . __($title, 'responsivas') . "</h3>";
    echo "</div>";
}

Html::header(
   __('Responsivas', 'responsivas'),
   $_SERVER['PHP_SELF'],
   'config',
   'plugins',
   'responsivas'
);

/* =============================
 * Configuration values
 * ============================= */
$maxSize     = 500 * 1024;
$allowedMime = ['image/png', 'image/jpeg'];
$logoPath    = PluginResponsivasPaths::logoPath();
$hasLogo = is_readable($logoPath);

$logoSizeKB  = 0;
$logoWidth   = 0;
$logoHeight  = 0;

if ($hasLogo) {
    $logoSizeKB = round(filesize($logoPath) / 1024, 2);
    [$logoWidth, $logoHeight] = getimagesize($logoPath);
}

/* =============================
 * Delete logo
 * ============================= */
if (isset($_POST['delete_logo'])) {

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

/* =============================
 * Apply changes and responses
 * ============================= */
if (isset($_POST['update'])) {
    // Save general configuration
    $values = [
        'timezone'             => (function() {
            $tz = Html::cleanInputText($_POST['timezone'] ?? '');
            return in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : 'America/Hermosillo';
         })(),
        'show_employee_number' => isset($_POST['show_employee_number']),
        'show_qr'              => isset($_POST['show_qr']),
        'pdf_compression'      => isset($_POST['pdf_compression']) ? 1 : 0,
        'pdf_protection'       => isset($_POST['pdf_protection'])  ? 1 : 0,
        'watermark_text'       => mb_substr(Html::cleanInputText(trim($_POST['watermark_text'] ?? '')), 0, 40),
        'watermark_opacity'    => max(5, min(100, (int)($_POST['watermark_opacity'] ?? 25))),
        'company_name'         => Html::cleanInputText(trim($_POST['company_name'] ?? '')),
        'currency'             => Html::cleanInputText(trim($_POST['currency'] ?? '$')),
        'testigo_1'            => (int)($_POST['testigo_1'] ?? 0),
        'testigo_2'            => (int)($_POST['testigo_2'] ?? 0),
        'representante'        => (int)($_POST['representante'] ?? 0),
        'cellphone_type_id'    => (int)($_POST['cellphone_type_id'] ?? 0),
        
        // Computer templates
        'pc_font_size'         => max(6, min(72, (int)($_POST['pc_font_size'] ?? 10))),
        'pc_titulo'            => Html::cleanInputText(trim($_POST['pc_titulo']  ?? '')),
        'pc_intro'             => trim($_POST['pc_intro']  ?? ''),
        'pc_cuerpo'            => trim($_POST['pc_cuerpo'] ?? ''),
        
        // Printer templates
        'pri_font_size'        => (int)($_POST['pri_font_size'] ?? 0),
        'pri_titulo'           => Html::cleanInputText(trim($_POST['pri_titulo']  ?? '')),
        'pri_intro'            => trim($_POST['pri_intro']  ?? ''),
        'pri_cuerpo'           => trim($_POST['pri_cuerpo'] ?? ''),
        
        // Phone templates        
        'pho_font_size'        => (int)($_POST['pho_font_size'] ?? 0),
        'pho_titulo'           => Html::cleanInputText(trim($_POST['pho_titulo']    ?? '')),
        'pho_apertura'         => trim($_POST['pho_apertura']   ?? ''),
        'pho_clausulas'        => trim($_POST['pho_clausulas']  ?? ''),
        'pho_testigos'         => trim($_POST['pho_testigos']   ?? ''),
        'pho_vida_util_factura' => trim($_POST['pho_vida_util_factura'] ?? ''),
        'pho_vida_util_sin'     => trim($_POST['pho_vida_util_sin']     ?? ''),
         
         // Email configuration
        'email_subject'        => Html::cleanInputText(trim($_POST['email_subject'] ?? '')),
        'email_body'           => trim($_POST['email_body']   ?? ''),
        'email_footer'         => trim($_POST['email_footer'] ?? ''),
    ];
    
    // Tipos de responsiva
    $types = ['pc', 'pri', 'pho'];
    
    // Posiciones del footer
    $positions = ['left_1', 'right_1', 'left_2', 'right_2'];
    
    foreach ($types as $type) {
        foreach ($positions as $pos) {
            $key = "{$type}_footer_{$pos}";
            $values[$key] = Html::cleanInputText($_POST[$key] ?? '');
        }
    }
    
    if ($values['cellphone_type_id'] === 0) {
       Session::addMessageAfterRedirect(
          __('You must select a valid phone type.', 'responsivas'),
          false,
          WARNING
       );
       Html::redirect($self);   // ← aborta antes de guardar
       return;
    }
    
    Config::setConfigurationValues('plugin_responsivas', $values);
    $logo_uploaded = false;

    // =============================
    // Subida de logo
    // =============================
    if (isset($_FILES['logo']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {

        $tmpFile = $_FILES['logo']['tmp_name'];
        $size    = $_FILES['logo']['size'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmpFile);
        finfo_close($finfo);

        if ($size > $maxSize) {

            Session::addMessageAfterRedirect(
                __('The file exceeds the maximum allowed size (500 KB).', 'responsivas'),
                false,
                ERROR
            );

        } elseif (!in_array($mime, $allowedMime)) {

            Session::addMessageAfterRedirect(
                __('Format not allowed. PNG or JPG only.', 'responsivas'),
                false,
                ERROR
            );

        } else {

            if (!is_dir(PluginResponsivasPaths::filesDir())) {
                mkdir(PluginResponsivasPaths::filesDir(), 0755, true);
            }

            if ($mime === 'image/jpeg') {
                $img = @imagecreatefromjpeg($tmpFile);
                if ($img !== false) {
                    imagepng($img, $logoPath);
                    imagedestroy($img);
                } else {
                    Session::addMessageAfterRedirect(__('Error processing JPG image', 'responsivas'), false, ERROR);
                }
            } else {
                move_uploaded_file($tmpFile, $logoPath);
            }

            chmod($logoPath, 0644);

            Session::addMessageAfterRedirect(
                __('Logo updated successfully.', 'responsivas'),
                false,
                INFO
            );

            $logo_uploaded = true;
        }
    }

    // =============================
    // Mensaje genérico si no hubo errores
    // =============================
    if (!$logo_uploaded) {
        Session::addMessageAfterRedirect(
            __('Configuration saved successfully.', 'responsivas'),
            false,
            INFO
        );
    }

    Html::redirect($self);
}

// =============================
// Helper de footers
// =============================
function responsivasFormatToolbar(): void
{
   echo "
<div class='d-flex gap-1 mb-1'>
  <button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='**'
          title='Negrita — **texto**'>
    <b>B</b>&nbsp;<small class='fw-normal opacity-75'>**</small>
  </button>
  <button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='*'
          title='Cursiva — *texto*'>
    <i>I</i>&nbsp;<small class='fw-normal opacity-75'>*</small>
  </button>
  <button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='__'
          title='Subrayado — __texto__'>
    <u>U</u>&nbsp;<small class='fw-normal opacity-75'>__</small>
  </button>
</div>";
}

function responsivasFooterFields(string $prefix, array $config): void {

    $rows = [
        [
            [
                'key'   => 'left_1',
                'label' => __('Footer left text', 'responsivas'),
                'icon'  => 'corner-up-left',
                'help'  => __('Example: Original: Company', 'responsivas')
            ],
            [
                'key'   => 'right_1',
                'label' => __('Top right', 'responsivas'),
                'icon'  => 'corner-up-right',
                'help'  => __('Example: Copy: Employee', 'responsivas')
            ]
        ],
        [
            [
                'key'   => 'left_2',
                'label' => __('Bottom left', 'responsivas'),
                'icon'  => 'corner-down-left',
                'help'  => __('Example: SIS-RESP-001', 'responsivas')
            ],
            [
                'key'   => 'right_2',
                'label' => __('Bottom right', 'responsivas'),
                'icon'  => 'corner-down-right',
                'help'  => __('Example: Rev 1.4 08/01/2026', 'responsivas')
            ]
        ]
    ];

    echo "<label class='form-label fw-bold'><i class='ti ti-receipt me-1'></i>" . __('Document footer', 'responsivas') . "</label>";
    echo "<div class='form-text text-muted mt-2 mb-3'>
          " . __('These lines will appear in the footer of the responsibility document.', 'responsivas') . "
          </div>";

    foreach ($rows as $row) {
        foreach ($row as $field) {

            $name  = "{$prefix}_footer_{$field['key']}";
            $value = Html::cleanInputText($config[$name] ?? '');

            echo "
            <div class='col-md-6 mb-3'>

              <label class='form-label fw-bold d-flex align-items-center gap-1'>
                <i class='ti ti-{$field['icon']}' aria-hidden='true'></i>
                {$field['label']}
              </label>

              <div class='d-flex gap-1 mb-1'>
                <button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='**' title='" . __('Bold — **text**', 'responsivas') . "'><b>B</b>&nbsp;<small class='fw-normal opacity-75'>**</small></button>
                <button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='*'  title='" . __('Italic — *text*', 'responsivas') . "'><i>I</i>&nbsp;<small class='fw-normal opacity-75'>*</small></button>
                <button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='__' title='" . __('Underline — __text__', 'responsivas') . "'><u>U</u>&nbsp;<small class='fw-normal opacity-75'>__</small></button>
              </div>

              <div class='input-group'>
                <span class='input-group-text'>
                  <i class='ti ti-edit'></i>
                </span>
                <input type='text'
                       class='form-control'
                       name='{$name}'
                       value='{$value}'>
              </div>

              <div class='form-text'>{$field['help']}</div>

            </div>";
        }
    }
}
// =============================
// Helper dropdow users
// =============================
function dropdownUser($name, $config) {
    User::dropdown([
        'name'   => $name,
        'value'  => $config[$name] ?? 0,
        'right'  => 'all',
        'active' => 1
    ]);
}

?>
<script>
// Vista precia de logo
function previewLogo(input) {
    const preview     = document.getElementById('logo-preview');
    const infoLabel   = document.getElementById('preview-size');
    const file = input.files[0];

    if (!file || !file.type.match('image.*')) {
        preview.classList.add('d-none');
        infoLabel.classList.add('d-none');
        return;
    }

    const reader = new FileReader();
    reader.onload = e => {

        const img = new Image();
        img.onload = () => {
            const width  = img.width;
            const height = img.height;
            const sizeKB = (file.size / 1024).toFixed(2);

            preview.src = e.target.result;
            preview.classList.remove('d-none');

            infoLabel.innerHTML =
                " . __('Preview dimensions: ', 'responsivas') . "' +
                width + ' × ' + height + ' px · ' +
                '" . __('Size: ', 'responsivas') . "<strong>' + sizeKB + ' KB</strong>';

            infoLabel.classList.remove('d-none');
        };
        img.src = e.target.result;
    };

    reader.readAsDataURL(file);
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

  // Save active tab when changing
  document.querySelectorAll('#responsivasTabs button[data-bs-toggle="tab"]').forEach(function (tab) {
    tab.addEventListener('shown.bs.tab', function (event) {
      const target = event.target.getAttribute('data-bs-target');
      if (target) {
        localStorage.setItem('responsivas_active_tab', target);
      }
    });
  });

  // Restore active tab on load
  const activeTab = localStorage.getItem('responsivas_active_tab');

  if (activeTab) {
    const trigger = document.querySelector('#responsivasTabs button[data-bs-target="' + activeTab + '"]');
    if (trigger && typeof bootstrap !== 'undefined') {
      const tab = new bootstrap.Tab(trigger);
      tab.show();
    }
  }

});
</script>

<?php
echo "<form id='responsivas-config-form' method='post' action='{$self}' enctype='multipart/form-data'>";

/* =====================================================
 * CARD PRINCIPAL
 * ===================================================== */
echo "<div class='card mt-2 shadow-sm'>";

echo "<div class='card-header mb-3 py-1 border-top rounded-0 position-relative'>";
echo "<div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
        <i class='fs-2x ti ti-settings'></i>
      </div>";

echo "<h4 class='card-title ms-5 mb-0'>" .
      __('Responsivas Settings', 'responsivas') .
     "</h4>";
echo "</div>";

/* =========================
 * TABS HEADER
 * ========================= */
echo "
<ul class='nav nav-tabs mb-3' id='responsivasTabs' role='tablist'>

  <li class='nav-item' role='presentation'>
    <button class='nav-link active'
            id='tab-general-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-general'
            type='button'
            role='tab'>
      <i class='ti ti-settings me-21'></i> " . __('General', 'responsivas') . "
    </button>
  </li>

  <li class='nav-item' role='presentation'>
    <button class='nav-link'
            id='tab-email-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-email'
            type='button'
            role='tab'>
      <i class='ti ti-mail me-1'></i> " . __('Email', 'responsivas') . "
    </button>
  </li>

  <li class='nav-item' role='presentation'>
    <button class='nav-link'
            id='tab-pc-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-pc'
            type='button'
            role='tab'>
      <i class='ti ti-device-desktop me-1'></i> " . __('Computers', 'responsivas') . "
    </button>
  </li>

  <li class='nav-item' role='presentation'>
    <button class='nav-link'
            id='tab-pri-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-pri'
            type='button'
            role='tab'>
      <i class='ti ti-printer me-1'></i> " . __('Printers', 'responsivas') . "
    </button>
  </li>

  <li class='nav-item' role='presentation'>
    <button class='nav-link'
            id='tab-pho-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-pho'
            type='button'
            role='tab'>
      <i class='ti ti-device-mobile me-1'></i> " . __('Phones', 'responsivas') . "
    </button>
  </li>

</ul>
";
/* =====================================================
 * VALIDA SI DEBUG ESTA ACTIVO
 * ===================================================== */
if (empty($config['cellphone_type_id'])) {
   echo "
   <div class='alert alert-warning d-flex align-items-start mb-4'>
      <i class='ti ti-alert-triangle me-2'></i>
      " . __('The phone type for responsibility documents has not been configured. Contact the administrator.', 'responsivas') . "
   </div>";
}

/* =========================
 * CONTENIDO DE TABS
 * ========================= */
echo "<div class='tab-content'>";
echo "<div class='tab-pane fade show active' id='tab-general' role='tabpanel'>";

/* =====================================================
 * SUB-CARD – OPCIONES DE RESPONSIVA
 * ===================================================== */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-file-settings', 'Responsibility document options');

echo "<div class='card-body'>";

/* ============================
 * Zona horaria
 * ============================ */
echo "<div class='mb-4'>";

// Label
echo "<label class='form-label fw-bold d-flex align-items-center mb-2'>
        <i class='ti ti-world me-2'></i>
        <span>" . __('Timezone for PDFs', 'responsivas') . "</span>
      </label>";

// Wrapper con icono
echo "<div class='input-group'>";

// Icono a la izquierda
echo "<span class='input-group-text'>
        <i class='ti ti-clock' aria-hidden='true'></i>
      </span>";

// Select
echo "<select name='timezone' class='form-select'>";

$currentTZ = $config['timezone'];

foreach (DateTimeZone::listIdentifiers() as $tz) {
    $selected = ($tz === $currentTZ) ? 'selected' : '';
    echo "<option value='{$tz}' {$selected}>{$tz}</option>";
}

echo "</select>"; // fin select
echo "</div>"; // fin input-group

// Ayuda debajo
echo "<div class='form-text'>";
echo sprintf(
   __('This timezone will be used to display dates and times in PDFs. At plugin installation it defaults to the server timezone. More information at: %s', 'responsivas'),
   "<a href='https://www.php.net/manual/timezones.php' target='_blank' rel='noopener noreferrer'>"
   . __('PHP timezone list', 'responsivas')
   . "</a>"
);
echo "</div>";

echo "</div>";

/* ============================
 * Switch número de empleado
 * ============================ */
echo "<div class='mb-4'>";
echo "<div class='row'>";

/* ===== Mostrar número de empleado ===== */
echo "<div class='col-md-6'>
        <label class='form-label fw-bold d-flex align-items-center'>
          <i class='ti ti-id-badge me-2'></i>
          " . __('Show employee number', 'responsivas') . "
        </label>
        <div class='form-check form-switch'>
          <input class='form-check-input'
                 type='checkbox'
                 id='show_employee_number'
                 name='show_employee_number'
                 value='1' " . ($config['show_employee_number'] ? 'checked' : '') . ">
            <label class='form-check-label' for='show_employee_number'>";
echo        __('Show employee number in the responsibility document', 'responsivas');
echo        "</label>
        </div>
      </div>";

/* ===== Mostrar QR ===== */
echo "<div class='col-md-6'>
        <label class='form-label fw-bold d-flex align-items-center'>
          <i class='ti ti-qrcode me-2'></i>
          " . __('Show QR', 'responsivas') . "
        </label>
        <div class='form-check form-switch'>
          <input class='form-check-input'
                 type='checkbox'
                 id='show_qr'
                 name='show_qr'
                 value='1' " . ($config['show_qr'] ? 'checked' : '') . ">
            <label class='form-check-label' for='show_qr'>";
echo        __('Show QR with asset URL in responsibility document', 'responsivas');
echo        "</label>
        </div>
      </div>";

/* ===== Comprimir PDF ===== */
echo "<div class='col-md-6'>
        <label class='form-label fw-bold d-flex align-items-center'>
          <i class='ti ti-file-zip me-2'></i>
          " . __('Compress PDF', 'responsivas') . "
        </label>
        <div class='form-check form-switch'>
          <input class='form-check-input'
                 type='checkbox'
                 id='pdf_compression'
                 name='pdf_compression'
                 value='1' " . ($config['pdf_compression'] ?? 1 ? 'checked' : '') . ">
            <label class='form-check-label' for='pdf_compression'>";
echo        __('Compress the generated PDF file', 'responsivas');
echo        "</label>
        </div>
      </div>";

/* ===== Proteger PDF (restringir copia/edición) ===== */
echo "<div class='col-md-6'>
        <label class='form-label fw-bold d-flex align-items-center'>
          <i class='ti ti-lock me-2'></i>
          " . __('Protect PDF', 'responsivas') . "
        </label>
        <div class='form-check form-switch'>
          <input class='form-check-input'
                 type='checkbox'
                 id='pdf_protection'
                 name='pdf_protection'
                 value='1' " . ($config['pdf_protection'] ?? 1 ? 'checked' : '') . ">
            <label class='form-check-label' for='pdf_protection'>";
echo        __('Restrict PDF copying and editing', 'responsivas');
echo        "</label>
        </div>
      </div>";

echo "</div>";
echo "</div>";

/* ===== Texto de marca de agua ===== */
echo "<div class='row mt-3'>
  <div class='col-md-8'>
    <label class='form-label fw-bold d-flex align-items-center'>
      <i class='ti ti-watermark me-2'></i>
      " . __('Watermark text', 'responsivas') . "
    </label>
    <div class='input-group'>
      <span class='input-group-text'><i class='ti ti-eye'></i></span>
      <input type='text'
             class='form-control'
             name='watermark_text'
             maxlength='40'
             placeholder='" . __('PREVIEW', 'responsivas') . "'
             value='" . Html::cleanInputText($config['watermark_text'] ?? '') . "'>
    </div>
    <div class='form-text'>" . __('Diagonal text shown on previews. Leave empty for the default.', 'responsivas') . "</div>
  </div>
  <div class='col-md-4'>
    <label class='form-label fw-bold d-flex align-items-center'>
      <i class='ti ti-adjustments me-2'></i>
      " . __('Watermark opacity (%)', 'responsivas') . "
    </label>
    <div class='input-group'>
      <span class='input-group-text'><i class='ti ti-percentage'></i></span>
      <input type='number'
             class='form-control'
             name='watermark_opacity'
             min='5' max='100' step='5'
             value='" . (int)($config['watermark_opacity'] ?? 25) . "'>
    </div>
    <div class='form-text'>" . __('5–100. Default: 25.', 'responsivas') . "</div>
  </div>
</div>";

/* ============================
 * Nombre de la empresa
 * ============================ */
echo "<div class='mb-4'>";

echo "<label class='form-label fw-bold'>
         <i class='ti ti-briefcase me-2'></i>
        " . __('Company name in responsibility documents', 'responsivas') . "
      </label>";

echo "<div class='input-group'>
        <span class='input-group-text'>
           <i class='ti ti-building' aria-hidden='true'></i>
        </span>
        <input type='text'
               name='company_name'
               class='form-control'
               value=\"" . Html::cleanInputText($config['company_name'] ?? '') . "\"
               required>
      </div>";

echo "<div class='form-text'>";
echo __('This name will appear in the responsibility document text.', 'responsivas');
echo "</div>";

echo "</div>";

/* ============================
 * Moneda
 * ============================ */
echo "<div class='mb-3'>";
echo "<label class='form-label fw-bold'>
        <i class='ti ti-currency-dollar me-1'></i>
        " . __('Currency symbol', 'responsivas') . "
      </label>";
echo "<div class='input-group' style='max-width:200px;'>
        <span class='input-group-text'><i class='ti ti-coin' aria-hidden='true'></i></span>
        <input type='text'
               name='currency'
               class='form-control'
               maxlength='10'
               placeholder='$'
               value=\"" . Html::cleanInputText($config['currency'] ?? '$') . "\">
      </div>";
echo "<div class='form-text'>" . __('Symbol or code that appears before the price in phone loan agreements (e.g.: $, USD, MXN, €).', 'responsivas') . "</div>";
echo "</div>";

/* ============================
 * Testigos
 * ============================ */
echo "<div class='mb-2'>";

echo "<label class='form-label fw-bold'>
        <i class='ti ti-users me-1'></i>
        " . __('Witnesses for phone loan agreements', 'responsivas') . "
      </label>";

echo "<div class='form-text mb-3'>";
echo __('Select two witnesses for the phone loan agreement.', 'responsivas');
echo "</div>";

// Contenedor en fila
echo "<div class='row'>";

/* Testigo 1 */
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label'><i class='ti ti-user me-1'></i>" . __('Witness 1', 'responsivas') . "</label>";
dropdownUser('testigo_1', $config);
echo "</div>";

/* Testigo 2 */
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label'><i class='ti ti-user me-1'></i>" . __('Witness 2', 'responsivas') . "</label>";
dropdownUser('testigo_2', $config);
echo "</div>";

echo "</div>"; // row
echo "</div>"; // testigos

/* ============================
 * Representante
 * ============================ */
echo "<div class='mb-2'>";

echo "<label class='form-label fw-bold'>
        <i class='ti ti-user-shield me-1'></i>
        " . __('Legal representative for phone loan agreements', 'responsivas') . "
      </label>";

echo "<div class='form-text mb-3'>";
echo __('Select the legal representative for the phone loan agreement.', 'responsivas');
echo "</div>";

/* Testigo 1 */
echo "<div class='mb-3'>";
echo "<label class='form-label'><i class='ti ti-user me-1'></i>" . __('Legal representative', 'responsivas') . "</label>";
dropdownUser('representante', $config);
echo "</div>";

echo "</div>"; // representante

echo "</div>"; // card-body
echo "</div>"; // card

/* =====================================================
 * SUB-CARD – LOGO
 * ===================================================== */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-photo', 'Institutional logo');

echo "<div class='card-body'>";

/* Advertencia si no hay logo */
if (!$hasLogo) {
    echo "<div class='alert alert-warning d-flex align-items-start mb-4'>";
    echo "<i class='ti ti-alert-triangle me-2 mt-1 text-warning' aria-hidden='true'></i>";
    echo "<div>";
    echo "<strong>" . __('No logo configured.', 'responsivas') . "</strong><br>";
    echo __('PDFs will be generated without a logo.', 'responsivas');
    echo "</div></div>";
}

echo "<div class='row'>";

/* =========================
 * Columna izquierda - Logo actual
 * ========================= */
echo "<div class='col-md-6'>";

if ($hasLogo) {

    echo "<div class='mb-4'>";

    echo "<div class='form-text mb-1'>";
    echo __('Click the image to download a backup copy.', 'responsivas');
    echo "</div>";

    echo "<label class='form-label fw-bold d-flex align-items-center'>
        <i class='ti ti-photo-check me-2' aria-hidden='true'></i>
        <span>" . __('Current logo', 'responsivas') . "</span>
      </label>";

    echo "<a href='" . PluginResponsivasPaths::logoUrl() . "'
            download='logo.png'
            title='" . __('Download logo', 'responsivas') . "'>";

    echo "<img src='" . PluginResponsivasPaths::logoUrl() . "&t=" . time() . "'
            class='img-fluid'
            style='max-height:80px;
                   padding:8px;
                   border-radius:6px;
                   border:1px solid var(--tblr-border-color);
                   cursor:pointer'>";

    echo "</a>";

    echo "<div class='form-text mt-1'>";
    echo "Dimensiones: {$logoWidth} × {$logoHeight} px · ";
    echo __('Size: ', 'responsivas') . "<strong>{$logoSizeKB} KB</strong>";
    echo "</div>";

    echo "<div class='mt-3'>";
    echo "<button type='button'
            class='btn btn-danger d-flex align-items-center gap-2'
            data-bs-toggle='modal'
            data-bs-target='#deleteLogoModal'>
      <i class='ti ti-trash'></i> " . __('Delete current logo', 'responsivas') . "
    </button>";
    echo "</div>";

    echo "</div>";
}

echo "</div>"; // col izquierda

/* =========================
 * Columna derecha - Vista previa
 * ========================= */
echo "<div class='col-md-6'>";

echo "<div class='mb-4'>";

echo "<label class='form-label fw-bold d-flex align-items-center'>
        <i class='ti ti-eye me-2' aria-hidden='true'></i>
        <span>" . __('Preview', 'responsivas') . "</span>
      </label>";

echo "<img id='logo-preview'
          class='img-fluid d-none'
          style='max-height:80px;
                 padding:8px;
                 border-radius:6px;
                 border:1px dashed var(--tblr-border-color)'>";

echo "<div id='preview-size' class='form-text d-none mt-1'></div>";

echo "<div class='form-text mb-1'>";
echo __('Load an image for a preview.', 'responsivas');
echo "</div>";

echo "</div>";

echo "</div>"; // col derecha

echo "</div>"; // row

/* Cargar logo */
echo "<div class='mb-4'>";
echo "<label class='form-label fw-bold d-flex align-items-center'>
        <i class='ti ti-upload me-2' aria-hidden='true'></i>
        <span>" . __('Upload new logo', 'responsivas') . "</span>
      </label>";
echo "<input type='file'
             name='logo'
             class='form-control mt-1'
             accept='image/png,image/jpeg'
             onchange='previewLogo(this)'>";
echo "<div class='form-text'>";
echo __('PNG / JPG · Max 500 KB · Will be saved as <b>logo.png</b>.', 'responsivas');
echo "</div>";
echo "</div>";
    echo "<div class='form-text mb-1'>";
    echo __('Once loaded and validated in preview, remember to save for it to take effect.', 'responsivas');
    echo "</div>";
echo "</div>"; // card-body
echo "</div>"; // sub-card

echo "</div>"; //Tab general

/* =========================
 * TAB CORREO
 * ========================= */
echo "<div class='tab-pane fade' id='tab-email' role='tabpanel'>";

echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-mail', __('Email options', 'responsivas'));

echo "<div class='card-body'>";

// ── Warning si GLPI no tiene correo configurado ──────────
$core_cfg  = Config::getConfigurationValues('core');
$mail_ok   = ($core_cfg['use_notifications']    ?? 0) == 1
          && ($core_cfg['notifications_mailing'] ?? 0) == 1;

if (!$mail_ok) {
   echo "<div class='alert alert-warning d-flex align-items-center mb-3' role='alert'>
      <i class='ti ti-alert-triangle me-2 fs-5'></i>
      <div>" . __('The GLPI mail server is not configured or email notifications are disabled. The send button will fail until you configure it in <strong>Configuration → Notifications → Email configuration</strong>.', 'responsivas') . "</div>
   </div>";
}

// ── Hint de variables disponibles ────────────────────────
$email_vars = [
   '{nombre}'  => __('Full name of the assigned user', 'responsivas'),
   '{empresa}' => __('Company name (configured in General)', 'responsivas'),
   '{fecha}'   => __('Document date in dd/mm/yyyy format', 'responsivas'),
];
responsivasVariableHints($email_vars);

$email_subject_val = htmlspecialchars($config['email_subject'] ?? '', ENT_QUOTES, 'UTF-8');
$email_body_val    = htmlspecialchars($config['email_body']    ?? '', ENT_QUOTES, 'UTF-8');
$email_footer_val  = htmlspecialchars($config['email_footer']  ?? '', ENT_QUOTES, 'UTF-8');

// ── Asunto ──────────────────────────────────────────────
echo "<div class='row mt-3 mb-3'>
  <div class='col-12'>
    <label class='form-label fw-semibold'>
      <i class='ti ti-pencil me-1'></i>
      " . __('Email subject', 'responsivas') . "
    </label>
    <input type='text'
           class='form-control'
           name='email_subject'
           maxlength='255'
           placeholder='" . __('Email subject', 'responsivas') . "'
           value='{$email_subject_val}'>
    <div class='form-text'>
      " . __('You can use the variables {nombre}, {empresa} and {fecha}.', 'responsivas') . "
    </div>
  </div>
</div>";

// ── Cuerpo ──────────────────────────────────────────────
echo "<div class='row mb-3'>
  <div class='col-12'>
    <label class='form-label fw-semibold'>
      <i class='ti ti-align-left me-1'></i>
      " . __('Email body', 'responsivas') . "
    </label>";
responsivasFormatToolbar();
echo "<textarea class='form-control'
              name='email_body'
              rows='5'
              placeholder='" . __('Email body', 'responsivas') . "'>" . $email_body_val . "</textarea>
    <div class='form-text'>
      " . __('You can use the variables {nombre}, {empresa} and {fecha}.', 'responsivas') . "
    </div>
  </div>
</div>";

// ── Pie ──────────────────────────────────────────────────
echo "<div class='row mb-3'>
  <div class='col-12'>
    <label class='form-label fw-semibold'>
      <i class='ti ti-align-bottom me-1'></i>
      " . __('Email footer', 'responsivas') . "
    </label>";
responsivasFormatToolbar();
echo "<textarea class='form-control'
              name='email_footer'
              rows='3'
              placeholder='" . __('Email footer (optional)', 'responsivas') . "'>" . $email_footer_val . "</textarea>
    <div class='form-text'>
      " . __('Optional. Appears at the bottom of the email, separated by a line. You can use {nombre}, {empresa} and {fecha}.', 'responsivas') . "
    </div>
  </div>
</div>";

echo "</div>"; // card-body
echo "</div>"; // card

// ── Botón de prueba ──────────────────────────────────────
$core_cfg = Config::getConfigurationValues('core');
$mail_ok  = ($core_cfg['use_notifications']    ?? 0) == 1
         && ($core_cfg['notifications_mailing'] ?? 0) == 1;

$test_action  = Plugin::getWebDir('responsivas') . '/front/send_mail.php';
$has_config   = !empty(trim($config['email_subject'] ?? '')) && !empty(trim($config['email_body'] ?? ''));
$btn_disabled = (!$mail_ok || !$has_config);
$btn_tooltip  = !$mail_ok
   ? __('GLPI mail server not configured', 'responsivas')
   : (!$has_config
      ? __('Configure the email subject and body first', 'responsivas')
      : __('Sends a test email to your registered GLPI address', 'responsivas'));
$btn_tooltip_esc = htmlspecialchars($btn_tooltip, ENT_QUOTES, 'UTF-8');
$btn_cls         = $btn_disabled ? "btn btn-warning disabled' aria-disabled='true' style='pointer-events:none;opacity:.65" : "btn btn-warning";

// Botón como formulario POST independiente con su propio CSRF token
$test_csrf_token = Session::getNewCSRFToken();
// El form del botón de prueba se declara con id pero SIN anidar en el form principal.
// El <button> usa form='test-mail-form' para asociarse al form externo via HTML5.
echo "<div class='card mt-2 rounded-0'>
   <div class='card-body d-flex align-items-center gap-3 flex-wrap'>
      <span data-bs-toggle='tooltip' title='{$btn_tooltip_esc}' class='d-inline-block'>
         <button type='submit' form='test-mail-form' class='{$btn_cls}'>
            <i class='ti ti-send me-2'></i>
            " . __('Send test email', 'responsivas') . "
         </button>
      </span>
      <span class='text-muted' style='font-size:0.85em;'>
         <i class='ti ti-info-circle me-1'></i>
         " . __('The email will be sent to the address registered in your GLPI profile.', 'responsivas') . "
      </span>
   </div>
</div>";

echo "</div>"; // tab-pane email

echo "<div class='tab-pane fade' id='tab-pc' role='tabpanel'>";

/* =========================
 * SUB-CARD – Ajustes Computadoras
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-device-desktop', 'Computer responsibility document settings');

echo "<div class='card-body'>";

// =========================
// Fuente y tamaño
// =========================
echo "<div class='row mt-3'>";

// -------------------------
// Fuente (fixed Helvetica)
// -------------------------
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-text-wrap'></i> Fuente usada
      </label>";
echo "<div class='input-group'>
        <span class='input-group-text'><i class='ti ti-typography'></i></span>
        <input type='text'
               class='form-control bg-body text-body'
               value='" . (Config::getConfigurationValue('core', 'pdffont')) . "'
               readonly>
      </div>";
echo "<div class='form-text'>" . __('Font used in PDFs, can be changed in GLPI settings', 'responsivas') . "</div>";
echo "</div>";

// -------------------------
// Tamaño de la fuente
// -------------------------
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-arrows-vertical'></i> " . __('Font size (pt)', 'responsivas') . "
      </label>";
echo "<div class='input-group'>
        <span class='input-group-text'><i class='ti ti-text-size'></i></span>
        <input type='number'
               class='form-control'
               name='pc_font_size'
               value='" . ((int)($config['pc_font_size'] ?? 10)) . "'
               min='6'
               max='24'>
      </div>";
echo "<div class='form-text'>" . __('Enter the font size for the PDF', 'responsivas') . "</div>";
echo "</div>";

echo "</div>"; // row

echo "<div class='row'>";
responsivasFooterFields('pc', $config);

echo "</div>"; // row

echo "</div>"; //card-body
echo "</div>"; //card

/* =========================
 * SUB-CARD – Plantilla de contenido PC
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-file-text', __('Document template', 'responsivas'));
echo "<div class='card-body'>";

// Variables disponibles para PC
responsivasVariableHints([
   '{nombre}'       => __('Full name of the assigned user', 'responsivas'),
   '{empresa}'      => __('Company', 'responsivas'),
   '{num_empleado}' => __('Employee number', 'responsivas'),
   '{activo}'       => __('Asset number', 'responsivas'),
   '{serie}'        => __('Serial number', 'responsivas'),
   '{marca}'        => __('Brand', 'responsivas'),
   '{modelo}'       => __('Model', 'responsivas'),
   '{tipo}'         => __('Equipment type', 'responsivas'),
   '{estado}'       => __('Condition / Status', 'responsivas'),
   '{fecha}'        => __('Document date', 'responsivas'),
   '{lugar}'        => __('City, State, Country', 'responsivas'),
]);

responsivasTemplateEditor(
   __('Document title', 'responsivas'),
   'pc_titulo',
   $config['pc_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO',
   __('Main heading text. Use **text** for bold.', 'responsivas'),
   1
);

responsivasTemplateEditor(
   __('Introduction paragraph', 'responsivas'),
   'pc_intro',
   $config['pc_intro'] ?? '',
   __('Introductory paragraph before the table. Use **text** for bold.', 'responsivas'),
   3
);

responsivasTemplateEditor(
   __('Body / Clauses', 'responsivas'),
   'pc_cuerpo',
   $config['pc_cuerpo'] ?? '',
   __('Legal text after the table. Lines with "1. text" → numbered list. Blank line → new paragraph. **text** → bold.', 'responsivas'),
   10
);

echo "</div>"; //card-body
echo "</div>"; //card


// ── Botón Vista Previa ─────────────────────────────────────────────────────
$_preview_url = Plugin::getWebDir('responsivas') . '/front/preview.php?type=pc';
echo "
<div class='card mt-3 rounded-0 border-primary'>
  <div class='card-body py-3 d-flex align-items-center justify-content-between'>
    <div class='text-muted' style='font-size:0.875rem;'>
      <i class='ti ti-info-circle me-1'></i>"
      . __('Generates a PDF preview using current settings. A watermark is applied.', 'responsivas') .
    "</div>
    <a href='{$_preview_url}' target='_blank'
       class='btn btn-primary d-flex align-items-center gap-2 ms-3 flex-shrink-0'>
      <i class='ti ti-eye'></i>
      " . __('Preview', 'responsivas') . "
    </a>
  </div>
</div>";
echo "</div>"; //Tab Computers

echo "<div class='tab-pane fade' id='tab-pri' role='tabpanel'>";

/* =========================
 * SUB-CARD – Ajustes Impresoras
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-printer', 'Printer responsibility document settings');
echo "<div class='card-body'>";

// =========================
// Fuente y tamaño
// =========================
echo "<div class='row mt-3'>";

// -------------------------
// Fuente (fixed Helvetica)
// -------------------------
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-text-wrap'></i> " . __('Font used', 'responsivas') . "
      </label>";
echo "<div class='input-group'>
        <span class='input-group-text'><i class='ti ti-typography'></i></span>
        <input type='text'
               class='form-control bg-body text-body'
               value='" . (Config::getConfigurationValue('core', 'pdffont')) . "'
               readonly>
      </div>";
echo "<div class='form-text'>" . __('Font used in PDFs, can be changed in GLPI settings', 'responsivas') . "</div>";
echo "</div>";

// -------------------------
// Tamaño de la fuente
// -------------------------
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-arrows-vertical'></i> " . __('Font size (pt)', 'responsivas') . "
      </label>";
echo "<div class='input-group'>
        <span class='input-group-text'><i class='ti ti-text-size'></i></span>
        <input type='number'
               class='form-control'
               name='pri_font_size'
               value='" . ((int)($config['pri_font_size'] ?? 10)) . "'
               min='6'
               max='24'>
      </div>";
echo "<div class='form-text'>" . __('Enter the font size for the PDF', 'responsivas') . "</div>";
echo "</div>";

echo "</div>"; // row

echo "<div class='row'>";
responsivasFooterFields('pri', $config);

echo "</div>"; // row

echo "</div>"; //card-body
echo "</div>"; //card

/* =========================
 * SUB-CARD – Plantilla de contenido Impresoras
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-file-text', __('Document template', 'responsivas'));
echo "<div class='card-body'>";

responsivasVariableHints([
   '{nombre}'       => __('Full name of the assigned user', 'responsivas'),
   '{empresa}'      => __('Company', 'responsivas'),
   '{num_empleado}' => __('Employee number', 'responsivas'),
   '{activo}'       => __('Asset number', 'responsivas'),
   '{serie}'        => __('Serial number', 'responsivas'),
   '{marca}'        => __('Brand', 'responsivas'),
   '{modelo}'       => __('Model', 'responsivas'),
   '{tipo}'         => __('Type', 'responsivas'),
   '{estado}'       => __('Condition / Status', 'responsivas'),
   '{fecha}'        => __('Document date', 'responsivas'),
   '{lugar}'        => __('City, State, Country', 'responsivas'),
]);

responsivasTemplateEditor(
   __('Document title', 'responsivas'),
   'pri_titulo',
   $config['pri_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO',
   __('Main heading text. Use **text** for bold.', 'responsivas'),
   1
);

responsivasTemplateEditor(
   __('Introduction paragraph', 'responsivas'),
   'pri_intro',
   $config['pri_intro'] ?? '',
   __('Introductory paragraph before the table. Use **text** for bold.', 'responsivas'),
   3
);

responsivasTemplateEditor(
   __('Body / Clauses', 'responsivas'),
   'pri_cuerpo',
   $config['pri_cuerpo'] ?? '',
   __('Legal text after the table. Lines with "1. text" → numbered list. Blank line → new paragraph. **text** → bold.', 'responsivas'),
   10
);

echo "</div>"; //card-body
echo "</div>"; //card


// ── Botón Vista Previa ─────────────────────────────────────────────────────
$_preview_url = Plugin::getWebDir('responsivas') . '/front/preview.php?type=pri';
echo "
<div class='card mt-3 rounded-0 border-primary'>
  <div class='card-body py-3 d-flex align-items-center justify-content-between'>
    <div class='text-muted' style='font-size:0.875rem;'>
      <i class='ti ti-info-circle me-1'></i>"
      . __('Generates a PDF preview using current settings. A watermark is applied.', 'responsivas') .
    "</div>
    <a href='{$_preview_url}' target='_blank'
       class='btn btn-primary d-flex align-items-center gap-2 ms-3 flex-shrink-0'>
      <i class='ti ti-eye'></i>
      " . __('Preview', 'responsivas') . "
    </a>
  </div>
</div>";
echo "</div>"; //Tab printers

echo "<div class='tab-pane fade' id='tab-pho' role='tabpanel'>";

/* =========================
 * SUB-CARD – Ajustes Teléfonos
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-device-mobile', 'Phone responsibility document settings');

echo "<div class='card-body'>";

// =========================
// Fuente y tamaño
// =========================
echo "<div class='row mt-3'>";

// -------------------------
// Tipo de teléfono a usar
// -------------------------
echo "<div class='col-md-6 mb-3'>";

echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-list-details'></i> " . __('Phone type for responsibility', 'responsivas') . "
      </label>";

echo "<div class='input-group'>";
echo "<span class='input-group-text'><i class='ti ti-device-mobile'></i></span>";

echo "<div class='flex-grow-1'>";

Dropdown::show('PhoneType', [
    'name'  => 'cellphone_type_id',
    'value' => (int)($config['cellphone_type_id'] ?? 0),
    'width' => '100%'
]);

echo "</div>";
echo "</div>";

echo "<div class='form-text'>
        " . __('Select the phone type that will be considered for generating responsibility documents.', 'responsivas') . "
      </div>";

echo "</div>";

// -------------------------
// Tamaño de la fuente
// -------------------------
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-arrows-vertical'></i> " . __('Font size (pt)', 'responsivas') . "
      </label>";
echo "<div class='input-group'>
        <span class='input-group-text'><i class='ti ti-text-size'></i></span>
        <input type='number'
               class='form-control'
               name='pho_font_size'
               value='" . ((int)($config['pho_font_size'] ?? 9)) . "'
               min='6'
               max='24'>
      </div>";
echo "<div class='form-text'>" . __('Enter the font size for the PDF', 'responsivas') . "</div>";
echo "</div>";

echo "</div>"; // row


echo "<div class='row'>";
responsivasFooterFields('pho', $config);

echo "</div>"; // row

echo "</div>"; // card-body
echo "</div>"; // card

/* =========================
 * SUB-CARD – Plantilla de contenido Teléfonos
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-file-text', __('Document template', 'responsivas'));
echo "<div class='card-body'>";

responsivasVariableHints([
   '{nombre}'            => __('User name', 'responsivas'),
   '{empresa}'           => __('Company', 'responsivas'),
   '{num_empleado}'      => __('Employee number', 'responsivas'),
   '{activo}'            => __('Asset number', 'responsivas'),
   '{serie_uuid}'        => __('UUID / Serial', 'responsivas'),
   '{imei}'              => __('IMEI', 'responsivas'),
   '{marca}'             => __('Brand', 'responsivas'),
   '{modelo}'            => __('Model', 'responsivas'),
   '{estado}'            => __('Condition', 'responsivas'),
   '{precio}'            => __('Purchase price', 'responsivas'),
   '{linea}'             => __('Line / mobile number', 'responsivas'),
   '{ram}'               => __('RAM', 'responsivas'),
   '{almacenamiento}'    => __('Storage', 'responsivas'),
   '{fecha}'             => __('Document date', 'responsivas'),
   '{hora}'              => __('Document time', 'responsivas'),
   '{lugar}'             => __('City, State, Country', 'responsivas'),
   '{direccion}'         => __('Entity address', 'responsivas'),
   '{cp}'                => __('ZIP / Postal code', 'responsivas'),
   '{representante}'     => __('Representative name', 'responsivas'),
   '{testigo1}'          => __('Witness 1', 'responsivas'),
   '{testigo2}'          => __('Witness 2', 'responsivas'),
   '{clausula_vida_util}'=> __('Useful life clause (auto-generated)', 'responsivas'),
]);

responsivasTemplateEditor(
   __('Document title', 'responsivas'),
   'pho_titulo',
   $config['pho_titulo'] ?? 'CONTRATO DE COMODATO',
   __('Contract heading. Use **text** for bold.', 'responsivas'),
   1
);

responsivasTemplateEditor(
   __('Opening paragraph', 'responsivas'),
   'pho_apertura',
   $config['pho_apertura'] ?? '',
   __('Appearance paragraph before the clauses. Use **text** for bold.', 'responsivas'),
   4
);

responsivasTemplateEditor(
   __('Clauses', 'responsivas'),
   'pho_clausulas',
   $config['pho_clausulas'] ?? '',
   __('One clause per line with format "1. text". Use {clausula_vida_util} for the useful life clause. **text** → bold.', 'responsivas'),
   15
);

responsivasTemplateEditor(
   __('Witnesses paragraph', 'responsivas'),
   'pho_testigos',
   $config['pho_testigos'] ?? '',
   __('Witnesses appearance text. Use **text** for bold.', 'responsivas'),
   3
);

responsivasTemplateEditor(
   __('Useful life clause (with invoice)', 'responsivas'),
   'pho_vida_util_factura',
   $config['pho_vida_util_factura'] ?? '',
   __('Used when the phone has an invoice and supplier. Variables: {fecha_compra}, {factura}, {proveedor}. Empty uses the default text.', 'responsivas'),
   2
);

responsivasTemplateEditor(
   __('Useful life clause (without invoice)', 'responsivas'),
   'pho_vida_util_sin',
   $config['pho_vida_util_sin'] ?? '',
   __('Used when the phone has no registered invoice or supplier. Empty uses the default text.', 'responsivas'),
   2
);

echo "</div>"; //card-body
echo "</div>"; //card


// ── Botón Vista Previa ─────────────────────────────────────────────────────
$_preview_url = Plugin::getWebDir('responsivas') . '/front/preview.php?type=pho';
echo "
<div class='card mt-3 rounded-0 border-primary'>
  <div class='card-body py-3 d-flex align-items-center justify-content-between'>
    <div class='text-muted' style='font-size:0.875rem;'>
      <i class='ti ti-info-circle me-1'></i>"
      . __('Generates a PDF preview using current settings. A watermark is applied.', 'responsivas') .
    "</div>
    <a href='{$_preview_url}' target='_blank'
       class='btn btn-primary d-flex align-items-center gap-2 ms-3 flex-shrink-0'>
      <i class='ti ti-eye'></i>
      " . __('Preview', 'responsivas') . "
    </a>
  </div>
</div>";
echo "</div>"; // tab-pane pho

/* Footer acciones */
echo "<div class='card-footer bg-light d-flex justify-content-end gap-2'>";

echo "<button type='submit'
             name='update'
             class='btn btn-primary d-flex align-items-center gap-2'>
        <i class='ti ti-device-floppy' aria-hidden='true'></i> " . __('Save', 'responsivas') . "
      </button>";

echo "</div>"; // footer
echo "</form>"; // cierre del form principal

// Form del correo de prueba — FUERA del form principal para evitar anidamiento
echo "<form id='test-mail-form' method='post' action='{$test_action}' style='display:none;'>
   <input type='hidden' name='_glpi_csrf_token' value='{$test_csrf_token}'>
      <input type='hidden' name='mode' value='test'>
</form>";

echo "</div>"; // main card

echo "
<div class='modal fade' id='deleteLogoModal' tabindex='-1' aria-hidden='true'>
  <div class='modal-dialog modal-dialog-centered'>
    <div class='modal-content'>

      <div class='modal-header'>
        <h5 class='modal-title'>
          <i class='ti ti-alert-triangle text-warning me-2'></i>
          " . __('Confirm deletion', 'responsivas') . "
        </h5>
        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
      </div>

      <div class='modal-body'>
        <p class='mb-2'>" . __('Are you sure you want to delete the current logo?', 'responsivas') . "</p>
        <div class='alert alert-warning d-flex align-items-center'>
          <i class='ti ti-info-circle me-2'></i>
          " . __('This action cannot be undone.', 'responsivas') . "
        </div>
      </div>

      <div class='modal-footer'>
        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
          <i class='ti ti-x'></i> " . __('Cancel', 'responsivas') . "
        </button>

        <button type='submit'
                name='delete_logo'
                value='1'
                class='btn btn-danger d-flex align-items-center gap-2'>
          <i class='ti ti-trash'></i> " . __('Delete logo', 'responsivas') . "
        </button>
      </div>

    </div>
  </div>
</div>
";

Html::closeForm();
Html::footer();