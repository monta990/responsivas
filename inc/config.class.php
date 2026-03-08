<?php

if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

require_once dirname(__DIR__) . '/inc/paths.class.php';
require_once dirname(__DIR__) . '/inc/helpers.php';
Session::checkRight('config', UPDATE);

$self = Plugin::getWebDir('responsivas') . '/front/config.form.php';
$config = Config::getConfigurationValues('plugin_responsivas');

function responsivasRibbonSubHeader(string $icon, string $title): void {
    echo "<div class='card-header mb-1 py-1 position-relative'>";
    echo "<div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
            <i class='fs-2x ti {$icon}' aria-hidden='true'></i>
          </div>";
    echo "<h3 class='card-subtitle ms-5 mb-0'>" . __($title) . "</h3>";
    echo "</div>";
}

Html::header(
   __('Responsivas'),
   $_SERVER['PHP_SELF'],
   'config',
   'plugins',
   'responsivas'
);

/* =============================
 * Configuración
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
 * Eliminar logo
 * ============================= */
if (isset($_POST['delete_logo'])) {

    if (is_file($logoPath) && !@unlink($logoPath)) {
        Session::addMessageAfterRedirect(
            __('No se pudo eliminar el logo.'),
            false,
            ERROR
        );
        Html::redirect($self);
        return;
    }

    Session::addMessageAfterRedirect(
        __('Logo eliminado correctamente.'),
        false,
        INFO
    );

    Html::redirect($self);
}

/* =============================
 * Aplicar cambios y respuestas
 * ============================= */
if (isset($_POST['update'])) {
    // Guardar configuración general
    $values = [
        'timezone'             => Html::cleanInputText($_POST['timezone']),
        'show_employee_number' => isset($_POST['show_employee_number']),
        'show_qr'              => isset($_POST['show_qr']),
        'company_name'         => Html::cleanInputText(trim($_POST['company_name'] ?? '')),
        'currency'             => Html::cleanInputText(trim($_POST['currency'] ?? '$')),
        'testigo_1'            => (int)($_POST['testigo_1'] ?? 0),
        'testigo_2'            => (int)($_POST['testigo_2'] ?? 0),
        'representante'        => (int)($_POST['representante'] ?? 0),
        'pc_font_size'         => (int)($_POST['pc_font_size'] ?? 0),
        'pc_titulo'            => Html::cleanInputText(trim($_POST['pc_titulo']  ?? '')),
        'pc_intro'             => trim($_POST['pc_intro']  ?? ''),
        'pc_cuerpo'            => trim($_POST['pc_cuerpo'] ?? ''),
        'pri_font_size'        => (int)($_POST['pri_font_size'] ?? 0),
        'pri_titulo'           => Html::cleanInputText(trim($_POST['pri_titulo']  ?? '')),
        'pri_intro'            => trim($_POST['pri_intro']  ?? ''),
        'pri_cuerpo'           => trim($_POST['pri_cuerpo'] ?? ''),
        'pho_font_size'        => (int)($_POST['pho_font_size'] ?? 0),
        'pho_titulo'           => Html::cleanInputText(trim($_POST['pho_titulo']    ?? '')),
        'pho_apertura'         => trim($_POST['pho_apertura']   ?? ''),
        'pho_clausulas'        => trim($_POST['pho_clausulas']  ?? ''),
        'pho_testigos'         => trim($_POST['pho_testigos']   ?? ''),
        'cellphone_type_id'    => (int)($_POST['cellphone_type_id'] ?? 0),
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
          __('Debes seleccionar un tipo de teléfono válido.'),
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
                __('El archivo excede el tamaño máximo permitido (500 KB).'),
                false,
                ERROR
            );

        } elseif (!in_array($mime, $allowedMime)) {

            Session::addMessageAfterRedirect(
                __('Formato no permitido. Solo PNG o JPG.'),
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
                    Session::addMessageAfterRedirect(__('Error al procesar imagen JPG'), false, ERROR);
                }
            } else {
                move_uploaded_file($tmpFile, $logoPath);
            }

            chmod($logoPath, 0644);

            Session::addMessageAfterRedirect(
                __('Logo actualizado correctamente.'),
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
            __('Configuración guardada correctamente.'),
            false,
            INFO
        );
    }

    Html::redirect($self);
}

// =============================
// Helper de footers
// =============================
function responsivasFooterFields(string $prefix, array $config): void {

    $rows = [
        [
            [
                'key'   => 'left_1',
                'label' => 'Superior izquierda',
                'icon'  => 'corner-up-left',
                'help'  => 'Ejemplo: Original: Empresa'
            ],
            [
                'key'   => 'right_1',
                'label' => 'Superior derecha',
                'icon'  => 'corner-up-right',
                'help'  => 'Ejemplo: Copia: Colaborador'
            ]
        ],
        [
            [
                'key'   => 'left_2',
                'label' => 'Inferior izquierda',
                'icon'  => 'corner-down-left',
                'help'  => 'Ejemplo: SIS-RESP-001'
            ],
            [
                'key'   => 'right_2',
                'label' => 'Inferior derecha',
                'icon'  => 'corner-down-right',
                'help'  => 'Ejemplo: Rev 1.4 08/01/2026'
            ]
        ]
    ];

    echo "<label class='form-label fw-bold'><i class='ti ti-receipt me-1'></i>Pie de página del documento</label>";
    echo "<div class='form-text text-muted mt-2 mb-3'>
          Estas líneas aparecerán en el pie de página de la responsiva.
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
                'Dimensiones de la vista previa: ' +
                width + ' × ' + height + ' px · ' +
                'Tamaño: <strong>' + sizeKB + ' KB</strong>';

            infoLabel.classList.remove('d-none');
        };
        img.src = e.target.result;
    };

    reader.readAsDataURL(file);
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

  // Guardar tab activo al cambiar
  document.querySelectorAll('#responsivasTabs button[data-bs-toggle="tab"]').forEach(function (tab) {
    tab.addEventListener('shown.bs.tab', function (event) {
      const target = event.target.getAttribute('data-bs-target');
      if (target) {
        localStorage.setItem('responsivas_active_tab', target);
      }
    });
  });

  // Restaurar tab activo al cargar
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
      __('Configuración del plugin Responsivas') .
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
      <i class='ti ti-settings me-21'></i> General
    </button>
  </li>

  <li class='nav-item' role='presentation'>
    <button class='nav-link'
            id='tab-email-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-email'
            type='button'
            role='tab'>
      <i class='ti ti-mail me-1'></i> " . __('Correo', 'responsivas') . "
    </button>
  </li>

  <li class='nav-item' role='presentation'>
    <button class='nav-link'
            id='tab-pc-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-pc'
            type='button'
            role='tab'>
      <i class='ti ti-device-desktop me-1'></i> Computadoras
    </button>
  </li>

  <li class='nav-item' role='presentation'>
    <button class='nav-link'
            id='tab-pri-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-pri'
            type='button'
            role='tab'>
      <i class='ti ti-printer me-1'></i> Impresoras
    </button>
  </li>

  <li class='nav-item' role='presentation'>
    <button class='nav-link'
            id='tab-pho-tab'
            data-bs-toggle='tab'
            data-bs-target='#tab-pho'
            type='button'
            role='tab'>
      <i class='ti ti-device-mobile me-1'></i> Teléfonos
    </button>
  </li>

</ul>
";
/* =====================================================
 * VALIDA SI DEBUG ESTA ACTIVO
 * ===================================================== */
if (Session::haveRight('config', UPDATE)
    && !empty($_SESSION['glpi_use_mode'])
    && ($_SESSION['glpi_use_mode'] & 2)
) {
    echo "<div class='alert alert-warning d-flex align-items-start mb-4'>";
    echo "<i class='ti ti-alert-triangle me-2 mt-1 text-warning' aria-hidden='true'></i>";
    echo "<div>";
    echo "<strong>" . __('GLPI en modo depuración.') . "</strong><br>";
    echo __('Esto podría provocar comportamientos inesperados.');
    echo "</div></div>";
}

if (empty($config['cellphone_type_id'])) {
   echo "
   <div class='alert alert-warning d-flex align-items-start mb-4'>
      <i class='ti ti-alert-triangle me-2'></i>
      " . __('El tipo de teléfono para responsivas no ha sido configurado. Contacta al administrador.', 'responsivas') . "
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
responsivasRibbonSubHeader('ti-file-settings', 'Opciones de la responsiva');

echo "<div class='card-body'>";

/* ============================
 * Zona horaria
 * ============================ */
echo "<div class='mb-4'>";

// Label
echo "<label class='form-label fw-bold d-flex align-items-center mb-2'>
        <i class='ti ti-world me-2'></i>
        <span>" . __('Zona horaria para los PDFs') . "</span>
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
   __('Esta zona horaria se usará para mostrar fechas y horas en los PDFs, al instalar el complemento se toma de manera predeterminada la del servidor en ese momento, más información en: %s'),
   "<a href='https://www.php.net/manual/timezones.php' target='_blank' rel='noopener noreferrer'>"
   . __('Lista de zonas horarias de PHP')
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
          " . __('Mostrar número de empleado', 'responsivas') . "
        </label>
        <div class='form-check form-switch'>
          <input class='form-check-input'
                 type='checkbox'
                 id='show_employee_number'
                 name='show_employee_number'
                 value='1' " . ($config['show_employee_number'] ? 'checked' : '') . ">
            <label class='form-check-label' for='show_employee_number'>";
echo        __('Mostrar número de empleado en la responsiva');
echo        "</label>
        </div>
      </div>";

/* ===== Mostrar QR ===== */
echo "<div class='col-md-6'>
        <label class='form-label fw-bold d-flex align-items-center'>
          <i class='ti ti-qrcode me-2'></i>
          " . __('Mostrar QR', 'responsivas') . "
        </label>
        <div class='form-check form-switch'>
          <input class='form-check-input'
                 type='checkbox'
                 id='show_qr'
                 name='show_qr'
                 value='1' " . ($config['show_qr'] ? 'checked' : '') . ">
            <label class='form-check-label' for='show_qr'>";
echo        __('Mostrar QR con Url de activo en responsiva');
echo        "</label>
        </div>
      </div>";

echo "</div>";
echo "</div>";

/* ============================
 * Nombre de la empresa
 * ============================ */
echo "<div class='mb-4'>";

echo "<label class='form-label fw-bold'>
         <i class='ti ti-briefcase me-2'></i>
        " . __('Nombre de la empresa en las responsivas') . "
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
echo __('Este nombre aparecerá dentro del texto de la responsiva.');
echo "</div>";

echo "</div>";

/* ============================
 * Moneda
 * ============================ */
echo "<div class='mb-3'>";
echo "<label class='form-label fw-bold'>
        <i class='ti ti-currency-dollar me-1'></i>
        " . __('Símbolo de moneda', 'responsivas') . "
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
echo "<div class='form-text'>" . __('Símbolo o código que aparece antes del precio en los comodatos de teléfono (ej: $, USD, MXN, €).', 'responsivas') . "</div>";
echo "</div>";

/* ============================
 * Testigos
 * ============================ */
echo "<div class='mb-2'>";

echo "<label class='form-label fw-bold'>
        <i class='ti ti-users me-1'></i>
        " . __('Testigos del comodato de celulares') . "
      </label>";

echo "<div class='form-text mb-3'>";
echo __('Selecciona dos testigos para el comodato de celulares.');
echo "</div>";

// Contenedor en fila
echo "<div class='row'>";

/* Testigo 1 */
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label'><i class='ti ti-user me-1'></i>" . __('Testigo 1') . "</label>";
dropdownUser('testigo_1', $config);
echo "</div>";

/* Testigo 2 */
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label'><i class='ti ti-user me-1'></i>" . __('Testigo 2') . "</label>";
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
        " . __('Representante legal del comodato de celulares') . "
      </label>";

echo "<div class='form-text mb-3'>";
echo __('Selecciona el representante legal para el comodato de celulares.');
echo "</div>";

/* Testigo 1 */
echo "<div class='mb-3'>";
echo "<label class='form-label'><i class='ti ti-user me-1'></i>" . __('Representante legal') . "</label>";
dropdownUser('representante', $config);
echo "</div>";

echo "</div>"; // representante

echo "</div>"; // card-body
echo "</div>"; // card

/* =====================================================
 * SUB-CARD – LOGO
 * ===================================================== */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-photo', 'Logo institucional');

echo "<div class='card-body'>";

/* Advertencia si no hay logo */
if (!$hasLogo) {
    echo "<div class='alert alert-warning d-flex align-items-start mb-4'>";
    echo "<i class='ti ti-alert-triangle me-2 mt-1 text-warning' aria-hidden='true'></i>";
    echo "<div>";
    echo "<strong>" . __('Sin logo configurado.') . "</strong><br>";
    echo __('Los PDFs se generarán sin logo.');
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
    echo __('Da clic en la imagen para descargar una copia de respaldo.');
    echo "</div>";

    echo "<label class='form-label fw-bold d-flex align-items-center'>
        <i class='ti ti-photo-check me-2' aria-hidden='true'></i>
        <span>" . __('Logo actual') . "</span>
      </label>";

    echo "<a href='" . PluginResponsivasPaths::logoUrl() . "'
            download='logo.png'
            title='" . __('Descargar logo') . "'>";

    echo "<img src='" . PluginResponsivasPaths::logoUrl() . "&t=" . time() . "'
            class='img-fluid'
            style='max-height:80px;
                   background:#fff;
                   padding:8px;
                   border-radius:6px;
                   border:1px solid #ddd;
                   cursor:pointer'>";

    echo "</a>";

    echo "<div class='form-text mt-1'>";
    echo "Dimensiones: {$logoWidth} × {$logoHeight} px · ";
    echo __('Tamaño: ') . "<strong>{$logoSizeKB} KB</strong>";
    echo "</div>";

    echo "<div class='mt-3'>";
    echo "<button type='button'
            class='btn btn-danger d-flex align-items-center gap-2'
            data-bs-toggle='modal'
            data-bs-target='#deleteLogoModal'>
      <i class='ti ti-trash'></i> " . __('Eliminar logo actual') . "
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
        <span>" . __('Vista previa') . "</span>
      </label>";

echo "<img id='logo-preview'
          class='img-fluid d-none'
          style='max-height:80px;
                 background:#fff;
                 padding:8px;
                 border-radius:6px;
                 border:1px dashed #bbb'>";

echo "<div id='preview-size' class='form-text d-none mt-1'></div>";

echo "<div class='form-text mb-1'>";
echo __('Para una vista previa carga una imagen.');
echo "</div>";

echo "</div>";

echo "</div>"; // col derecha

echo "</div>"; // row

/* Cargar logo */
echo "<div class='mb-4'>";
echo "<label class='form-label fw-bold d-flex align-items-center'>
        <i class='ti ti-upload me-2' aria-hidden='true'></i>
        <span>" . __('Cargar nuevo logo') . "</span>
      </label>";
echo "<input type='file'
             name='logo'
             class='form-control mt-1'
             accept='image/png,image/jpeg'
             onchange='previewLogo(this)'>";
echo "<div class='form-text'>";
echo __('PNG / JPG · Máx 500 KB · Se guardará como <b>logo.png</b>.');
echo "</div>";
echo "</div>";
    echo "<div class='form-text mb-1'>";
    echo __('Una vez cargado y validado en vista previa, recuerda guardar para que se aplique.');
    echo "</div>";
echo "</div>"; // card-body
echo "</div>"; // sub-card

echo "</div>"; //Tab general

/* =========================
 * TAB CORREO
 * ========================= */
echo "<div class='tab-pane fade' id='tab-email' role='tabpanel'>";

echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-mail', __('Opciones del correo electrónico', 'responsivas'));

echo "<div class='card-body'>";

// ── Warning si GLPI no tiene correo configurado ──────────
$core_cfg  = Config::getConfigurationValues('core');
$mail_ok   = ($core_cfg['use_notifications']    ?? 0) == 1
          && ($core_cfg['notifications_mailing'] ?? 0) == 1;

if (!$mail_ok) {
   echo "<div class='alert alert-warning d-flex align-items-center mb-3' role='alert'>
      <i class='ti ti-alert-triangle me-2 fs-5'></i>
      <div>" . __('El servidor de correo de GLPI no está configurado o las notificaciones por correo están desactivadas. El botón de envío fallará hasta que lo configures en <strong>Configuración → Notificaciones → Configuración de correos</strong>.', 'responsivas') . "</div>
   </div>";
}

// ── Hint de variables disponibles ────────────────────────
echo "<div class='alert alert-info d-flex align-items-start mb-3' role='alert'>
   <i class='ti ti-variable me-2 fs-5 mt-1'></i>
   <div style='font-size:0.85rem;'>
      <strong>" . __('Variables disponibles', 'responsivas') . ":</strong>
      <span class='text-muted ms-2'>" . __('Usa **texto** para negrita', 'responsivas') . "</span><br>
      <code class='me-1'>{nombre}</code> &mdash; " . __('Nombre completo del usuario', 'responsivas') . "<br>
      <code class='me-1'>{empresa}</code> &mdash; " . __('Nombre de la empresa (configurado en General)', 'responsivas') . "<br>
      <code class='me-1'>{fecha}</code> &mdash; " . __('Fecha del día en formato dd/mm/aaaa', 'responsivas') . "
   </div>
</div>";

$email_subject_val = htmlspecialchars($config['email_subject'] ?? '', ENT_QUOTES, 'UTF-8');
$email_body_val    = htmlspecialchars($config['email_body']    ?? '', ENT_QUOTES, 'UTF-8');
$email_footer_val  = htmlspecialchars($config['email_footer']  ?? '', ENT_QUOTES, 'UTF-8');

// ── Asunto ──────────────────────────────────────────────
echo "<div class='row mt-3 mb-3'>
  <div class='col-12'>
    <label class='form-label fw-semibold'>
      <i class='ti ti-pencil me-1'></i>
      " . __('Asunto del correo', 'responsivas') . "
    </label>
    <input type='text'
           class='form-control'
           name='email_subject'
           maxlength='255'
           placeholder='" . __('Asunto del correo', 'responsivas') . "'
           value='{$email_subject_val}'>
    <div class='form-text'>
      " . __('Puedes usar las variables {nombre}, {empresa} y {fecha}.', 'responsivas') . "
    </div>
  </div>
</div>";

// ── Cuerpo ──────────────────────────────────────────────
echo "<div class='row mb-3'>
  <div class='col-12'>
    <label class='form-label fw-semibold'>
      <i class='ti ti-align-left me-1'></i>
      " . __('Cuerpo del correo', 'responsivas') . "
    </label>
    <textarea class='form-control'
              name='email_body'
              rows='5'
              placeholder='" . __('Cuerpo del correo', 'responsivas') . "'>" . $email_body_val . "</textarea>
    <div class='form-text'>
      " . __('Puedes usar las variables {nombre}, {empresa} y {fecha}.', 'responsivas') . "
    </div>
  </div>
</div>";

// ── Pie ──────────────────────────────────────────────────
echo "<div class='row mb-3'>
  <div class='col-12'>
    <label class='form-label fw-semibold'>
      <i class='ti ti-align-bottom me-1'></i>
      " . __('Pie del correo', 'responsivas') . "
    </label>
    <textarea class='form-control'
              name='email_footer'
              rows='3'
              placeholder='" . __('Pie del correo (opcional)', 'responsivas') . "'>" . $email_footer_val . "</textarea>
    <div class='form-text'>
      " . __('Opcional. Aparece al pie del correo, separado por una línea. Puedes usar {nombre}, {empresa} y {fecha}.', 'responsivas') . "
    </div>
  </div>
</div>";

echo "</div>"; // card-body
echo "</div>"; // card

// ── Botón de prueba ──────────────────────────────────────
$core_cfg = Config::getConfigurationValues('core');
$mail_ok  = ($core_cfg['use_notifications']    ?? 0) == 1
         && ($core_cfg['notifications_mailing'] ?? 0) == 1;

$test_action  = Plugin::getWebDir('responsivas') . '/front/send_test_mail.php';
$has_config   = !empty(trim($config['email_subject'] ?? '')) && !empty(trim($config['email_body'] ?? ''));
$btn_disabled = (!$mail_ok || !$has_config);
$btn_tooltip  = !$mail_ok
   ? __('Servidor de correo de GLPI no configurado', 'responsivas')
   : (!$has_config
      ? __('Configure el asunto y cuerpo del correo primero', 'responsivas')
      : __('Envía un correo de prueba a tu dirección registrada en GLPI', 'responsivas'));
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
            " . __('Enviar correo de prueba', 'responsivas') . "
         </button>
      </span>
      <span class='text-muted' style='font-size:0.85em;'>
         <i class='ti ti-info-circle me-1'></i>
         " . __('El correo se enviará a la dirección registrada en tu perfil de GLPI.', 'responsivas') . "
      </span>
   </div>
</div>";

echo "</div>"; // tab-pane email

echo "<div class='tab-pane fade' id='tab-pc' role='tabpanel'>";

/* =========================
 * SUB-CARD – Ajustes Computadoras
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-device-desktop', 'Ajustes responsiva computadoras');

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
               class='form-control'
               value='" . (Config::getConfigurationValue('core', 'pdffont')) . "'
               disabled>
      </div>";
echo "<div class='form-text'>Fuente usada en PDFs, se puede cambiar en ajustes de GLPI</div>";
echo "</div>";

// -------------------------
// Tamaño de la fuente
// -------------------------
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-arrows-vertical'></i> Tamaño de la fuente (pt)
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
echo "<div class='form-text'>Ingresa el tamaño de fuente para el PDF</div>";
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
responsivasRibbonSubHeader('ti-file-text', __('Plantilla del documento', 'responsivas'));
echo "<div class='card-body'>";

// Variables disponibles para PC
responsivasVariableHints([
   '{nombre}'       => __('Nombre completo del usuario', 'responsivas'),
   '{empresa}'      => __('Empresa', 'responsivas'),
   '{num_empleado}' => __('Número de empleado', 'responsivas'),
   '{activo}'       => __('Número de activo', 'responsivas'),
   '{serie}'        => __('Número de serie', 'responsivas'),
   '{marca}'        => __('Marca', 'responsivas'),
   '{modelo}'       => __('Modelo', 'responsivas'),
   '{tipo}'         => __('Tipo de equipo', 'responsivas'),
   '{estado}'       => __('Condición / Estado', 'responsivas'),
   '{fecha}'        => __('Fecha del documento', 'responsivas'),
   '{lugar}'        => __('Ciudad, Estado, País', 'responsivas'),
]);

responsivasTemplateEditor(
   __('Título del documento', 'responsivas'),
   'pc_titulo',
   $config['pc_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO',
   __('Texto del encabezado principal. Usa **texto** para negrita.', 'responsivas'),
   1
);

responsivasTemplateEditor(
   __('Párrafo de introducción', 'responsivas'),
   'pc_intro',
   $config['pc_intro'] ?? '',
   __('Párrafo introductorio antes de la tabla. Usa **texto** para negrita.', 'responsivas'),
   3
);

responsivasTemplateEditor(
   __('Cuerpo / Cláusulas', 'responsivas'),
   'pc_cuerpo',
   $config['pc_cuerpo'] ?? '',
   __('Texto legal después de la tabla. Líneas con "1. texto" → lista numerada. Línea en blanco → nuevo párrafo. **texto** → negrita.', 'responsivas'),
   10
);

echo "</div>"; //card-body
echo "</div>"; //card

echo "</div>"; //Tab Computers

echo "<div class='tab-pane fade' id='tab-pri' role='tabpanel'>";

/* =========================
 * SUB-CARD – Ajustes Impresoras
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-printer', 'Ajustes responsiva impresoras');
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
               class='form-control'
               value='" . (Config::getConfigurationValue('core', 'pdffont')) . "'
               disabled>
      </div>";
echo "<div class='form-text'>Fuente usada en PDFs, se puede cambiar en ajustes de GLPI</div>";
echo "</div>";

// -------------------------
// Tamaño de la fuente
// -------------------------
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-arrows-vertical'></i> Tamaño de la fuente (pt)
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
echo "<div class='form-text'>Ingresa el tamaño de fuente para el PDF</div>";
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
responsivasRibbonSubHeader('ti-file-text', __('Plantilla del documento', 'responsivas'));
echo "<div class='card-body'>";

responsivasVariableHints([
   '{nombre}'       => __('Nombre completo del usuario', 'responsivas'),
   '{empresa}'      => __('Empresa', 'responsivas'),
   '{num_empleado}' => __('Número de empleado', 'responsivas'),
   '{activo}'       => __('Número de activo', 'responsivas'),
   '{serie}'        => __('Número de serie', 'responsivas'),
   '{marca}'        => __('Marca', 'responsivas'),
   '{modelo}'       => __('Modelo', 'responsivas'),
   '{tipo}'         => __('Tipo', 'responsivas'),
   '{estado}'       => __('Condición / Estado', 'responsivas'),
   '{fecha}'        => __('Fecha del documento', 'responsivas'),
   '{lugar}'        => __('Ciudad, Estado, País', 'responsivas'),
]);

responsivasTemplateEditor(
   __('Título del documento', 'responsivas'),
   'pri_titulo',
   $config['pri_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO',
   __('Texto del encabezado principal. Usa **texto** para negrita.', 'responsivas'),
   1
);

responsivasTemplateEditor(
   __('Párrafo de introducción', 'responsivas'),
   'pri_intro',
   $config['pri_intro'] ?? '',
   __('Párrafo introductorio antes de la tabla. Usa **texto** para negrita.', 'responsivas'),
   3
);

responsivasTemplateEditor(
   __('Cuerpo / Cláusulas', 'responsivas'),
   'pri_cuerpo',
   $config['pri_cuerpo'] ?? '',
   __('Texto legal después de la tabla. Líneas con "1. texto" → lista numerada. Línea en blanco → nuevo párrafo. **texto** → negrita.', 'responsivas'),
   10
);

echo "</div>"; //card-body
echo "</div>"; //card

echo "</div>"; //Tab printers

echo "<div class='tab-pane fade' id='tab-pho' role='tabpanel'>";

/* =========================
 * SUB-CARD – Ajustes Teléfonos
 * ========================= */
echo "<div class='card mt-2 rounded-0'>";
responsivasRibbonSubHeader('ti-device-mobile', 'Ajustes responsiva teléfonos');

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
        <i class='ti ti-list-details'></i> Tipo de teléfono para responsiva
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
        Selecciona el tipo de teléfono que será considerado para generar responsivas.
      </div>";

echo "</div>";

// -------------------------
// Tamaño de la fuente
// -------------------------
echo "<div class='col-md-6 mb-3'>";
echo "<label class='form-label fw-bold d-flex align-items-center gap-1'>
        <i class='ti ti-arrows-vertical'></i> Tamaño de la fuente (pt)
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
echo "<div class='form-text'>Ingresa el tamaño de fuente para el PDF</div>";
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
responsivasRibbonSubHeader('ti-file-text', __('Plantilla del documento', 'responsivas'));
echo "<div class='card-body'>";

responsivasVariableHints([
   '{nombre}'            => __('Nombre del usuario', 'responsivas'),
   '{empresa}'           => __('Empresa', 'responsivas'),
   '{num_empleado}'      => __('Número de empleado', 'responsivas'),
   '{activo}'            => __('Número de activo', 'responsivas'),
   '{serie_uuid}'        => __('UUID / Serie', 'responsivas'),
   '{imei}'              => __('IMEI', 'responsivas'),
   '{marca}'             => __('Marca', 'responsivas'),
   '{modelo}'            => __('Modelo', 'responsivas'),
   '{estado}'            => __('Condición', 'responsivas'),
   '{precio}'            => __('Precio de compra', 'responsivas'),
   '{linea}'             => __('Número de línea / móvil', 'responsivas'),
   '{ram}'               => __('RAM', 'responsivas'),
   '{almacenamiento}'    => __('Almacenamiento', 'responsivas'),
   '{fecha}'             => __('Fecha del documento', 'responsivas'),
   '{hora}'              => __('Hora del documento', 'responsivas'),
   '{lugar}'             => __('Ciudad, Estado, País', 'responsivas'),
   '{direccion}'         => __('Dirección de la entidad', 'responsivas'),
   '{cp}'                => __('Código postal', 'responsivas'),
   '{representante}'     => __('Nombre del representante', 'responsivas'),
   '{testigo1}'          => __('Testigo 1', 'responsivas'),
   '{testigo2}'          => __('Testigo 2', 'responsivas'),
   '{clausula_vida_util}'=> __('Cláusula de vida útil (auto-generada)', 'responsivas'),
]);

responsivasTemplateEditor(
   __('Título del documento', 'responsivas'),
   'pho_titulo',
   $config['pho_titulo'] ?? 'CONTRATO DE COMODATO',
   __('Encabezado del contrato. Usa **texto** para negrita.', 'responsivas'),
   1
);

responsivasTemplateEditor(
   __('Párrafo de apertura', 'responsivas'),
   'pho_apertura',
   $config['pho_apertura'] ?? '',
   __('Párrafo de comparecencia antes de las cláusulas. Usa **texto** para negrita.', 'responsivas'),
   4
);

responsivasTemplateEditor(
   __('Cláusulas', 'responsivas'),
   'pho_clausulas',
   $config['pho_clausulas'] ?? '',
   __('Una cláusula por línea con formato "1. texto". Usa {clausula_vida_util} para la cláusula de vida útil. **texto** → negrita.', 'responsivas'),
   15
);

responsivasTemplateEditor(
   __('Párrafo de testigos', 'responsivas'),
   'pho_testigos',
   $config['pho_testigos'] ?? '',
   __('Texto de comparecencia de testigos. Usa **texto** para negrita.', 'responsivas'),
   3
);

echo "</div>"; //card-body
echo "</div>"; //card

echo "</div>"; // tab-pane

/* Footer acciones */
echo "<div class='card-footer bg-light d-flex justify-content-end gap-2'>";

echo "<button type='submit'
             name='update'
             class='btn btn-primary d-flex align-items-center gap-2'>
        <i class='ti ti-device-floppy' aria-hidden='true'></i> " . __('Guardar') . "
      </button>";

echo "</div>"; // footer
echo "</form>"; // cierre del form principal

// Form del correo de prueba — FUERA del form principal para evitar anidamiento
echo "<form id='test-mail-form' method='post' action='{$test_action}' style='display:none;'>
   <input type='hidden' name='_glpi_csrf_token' value='{$test_csrf_token}'>
</form>";

echo "</div>"; // main card

echo "
<div class='modal fade' id='deleteLogoModal' tabindex='-1' aria-hidden='true'>
  <div class='modal-dialog modal-dialog-centered'>
    <div class='modal-content'>

      <div class='modal-header'>
        <h5 class='modal-title'>
          <i class='ti ti-alert-triangle text-warning me-2'></i>
          " . __('Confirmar eliminación') . "
        </h5>
        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
      </div>

      <div class='modal-body'>
        <p class='mb-2'>" . __('¿Estás seguro de que deseas eliminar el logo actual?') . "</p>
        <div class='alert alert-warning d-flex align-items-center'>
          <i class='ti ti-info-circle me-2'></i>
          " . __('Esta acción no se puede deshacer.') . "
        </div>
      </div>

      <div class='modal-footer'>
        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
          <i class='ti ti-x'></i> " . __('Cancelar') . "
        </button>

        <button type='submit'
                name='delete_logo'
                value='1'
                class='btn btn-danger d-flex align-items-center gap-2'>
          <i class='ti ti-trash'></i> " . __('Eliminar logo') . "
        </button>
      </div>

    </div>
  </div>
</div>
";

Html::closeForm();
Html::footer();