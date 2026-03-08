<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/pdf.class.php';

/**
 * PluginResponsivasPdfBuilder
 *
 * Fuente única de verdad para la construcción de PDFs de responsivas.
 * Tanto los archivos front (descarga directa) como el generador de correo
 * usan este builder — si modificas el contenido de un PDF aquí, el cambio
 * aplica automáticamente en ambos contextos.
 *
 * Cada método build* devuelve ['pdf' => PluginResponsivasPDF, 'filename' => string]
 * o lanza RuntimeException con un mensaje descriptivo en caso de error.
 */
class PluginResponsivasPdfBuilder
{
   /* =====================================================
    * HELPER: nombre de archivo con fecha
    * ===================================================== */
   public static function makeFilename(string $prefix, string $full_name): string
   {
      $safe = trim(preg_replace('/_+/', '_', preg_replace('/[^A-Za-z0-9]/', '_', $full_name)), '_');
      $date = date('Y-m-d');
      return "{$prefix}_{$safe}_{$date}.pdf";
   }

   /* =====================================================
    * HELPER: usuario creador
    * ===================================================== */
   private static function getCreator(): string
   {
      if ($uid = Session::getLoginUserID()) {
         $u = new User();
         if ($u->getFromDB($uid)) {
            return $u->getFriendlyName();
         }
      }
      return 'GLPI';
   }

   /* =====================================================
    * HELPER: cabecera de tabla de dispositivos periféricos
    * ===================================================== */
   private static function appendDevicesHeader(string &$html, bool &$printed, string $th_bg): void
   {
      if ($printed) return;
      $html .= <<<HTML
<tr style="background-color:{$th_bg};">
  <td width="20%"><strong>Dispositivo</strong></td>
  <td width="20%"><strong>Marca</strong></td>
  <td width="20%"><strong>Modelo</strong></td>
  <td width="20%"><strong>Serie / Activo</strong></td>
  <td width="20%"><strong>Condición</strong></td>
</tr>
HTML;
      $printed = true;
   }

   /* =====================================================
    * PDF DE COMPUTADORAS
    * ===================================================== */

   /* =====================================================
    * HELPER: validación de campos de plantilla vacíos
    * Lanza RuntimeException con lista de campos faltantes
    * antes de intentar generar el PDF.
    * ===================================================== */
   public static function validateTemplates(string $type, array $config): void
   {
      $required = [
         'pc'  => ['pc_titulo', 'pc_intro', 'pc_cuerpo'],
         'pri' => ['pri_titulo', 'pri_intro', 'pri_cuerpo'],
         'pho' => ['pho_titulo', 'pho_apertura', 'pho_clausulas', 'pho_testigos'],
      ];

      $labels = [
         'pc_titulo'      => __('Título (Computadora)', 'responsivas'),
         'pc_intro'       => __('Introducción (Computadora)', 'responsivas'),
         'pc_cuerpo'      => __('Cuerpo / Cláusulas (Computadora)', 'responsivas'),
         'pri_titulo'     => __('Título (Impresora)', 'responsivas'),
         'pri_intro'      => __('Introducción (Impresora)', 'responsivas'),
         'pri_cuerpo'     => __('Cuerpo / Cláusulas (Impresora)', 'responsivas'),
         'pho_titulo'     => __('Título (Teléfono)', 'responsivas'),
         'pho_apertura'   => __('Párrafo de apertura (Teléfono)', 'responsivas'),
         'pho_clausulas'  => __('Cláusulas (Teléfono)', 'responsivas'),
         'pho_testigos'   => __('Párrafo de testigos (Teléfono)', 'responsivas'),
      ];

      $missing = [];
      foreach ($required[$type] ?? [] as $key) {
         if (empty(trim($config[$key] ?? ''))) {
            $missing[] = $labels[$key] ?? $key;
         }
      }

      if (!empty($missing)) {
         throw new RuntimeException(sprintf(
            __('La plantilla del documento tiene campos vacíos. Completa los siguientes campos en Configuración → Responsivas: %s', 'responsivas'),
            implode(', ', $missing)
         ));
      }
   }

   public static function buildComputerPdf(int $user_id): array
   {
      global $DB, $CFG_GLPI;

      $config = Config::getConfigurationValues('plugin_responsivas');
      self::validateTemplates('pc', $config);

      $user = new User();
      if (!$user->getFromDB($user_id)) {
         throw new RuntimeException(__('Usuario no encontrado.'));
      }

      $computers = [];
      foreach ((new Computer())->find(['users_id' => $user_id, 'is_deleted' => 0]) as $row) {
         $comp         = new Computer();
         $comp->fields = $row;
         $computers[]  = $comp;
      }
      if (empty($computers)) {
         throw new RuntimeException(__('El usuario no tiene equipos asignados.'));
      }

      $entity = new Entity();
      if (!$entity->getFromDB(Session::getActiveEntity())) {
         throw new RuntimeException(__('No se pudo obtener la entidad.'));
      }

      $location = e(implode(', ', array_filter([
         $entity->fields['town'],
         $entity->fields['state'],
         $entity->fields['country'],
      ])));

      $full_name       = $user->getFriendlyName();
      $employee_number = e($user->fields['registration_number'] ?? '');
      $show_employee   = (int)($config['show_employee_number'] ?? 0);
      $company_name    = e($config['company_name'] ?? '');
      $employee_line   = ($show_employee && $employee_number) ? "Empleado No: {$employee_number}<br>" : '';
      $creator         = self::getCreator();

      $th_bg = '#E6E6E6';
      $td_bg = '#FFFFFF';

      $pdf = new PluginResponsivasPDF('P', 'mm', 'LETTER');
      $pdf->setDocumentType('pc_font_size', 'pc');
      $pdf->fecha_header = fechaATexto($_SESSION['glpi_currenttime'], $config['timezone']);
      $pdf->location     = $location;
      $pdf->SetCreator('GLPI');
      $pdf->SetAuthor($creator);
      $pdf->SetTitle('Responsiva Computadora - ' . $full_name);
      $pdf->SetPDFVersion('1.4');
      $pdf->SetSubject('Responsiva de computadora');
      $pdf->SetKeywords('responsiva, computadora, activos, TI');
      $pdf->SetMargins(15, 25, 15);
      $pdf->SetAutoPageBreak(true, 30);
      $pdf->SetPrintHeader(true);
      $pdf->SetPrintFooter(true);
      $pdf->setCompression(true);
      $pdf->setFontSubsetting(true);
      $pdf->SetProtection(['copy', 'modify'], '', null);
      $pdf->SetFont(Config::getConfigurationValue('core', 'pdffont'), '', (int)$config['pc_font_size']);

      $full_name_safe = e($full_name);

      foreach ($computers as $comp) {
         $pdf->AddPage();
         $page = $pdf->getPage();
         $pdf->setQrForPage($page, $CFG_GLPI['url_base'] . '/front/computer.form.php?id=' . $comp->getID());

         $marca         = e(ddn($comp->fields['manufacturers_id'], 'glpi_manufacturers'));
         $modelo        = e(ddn($comp->fields['computermodels_id'], 'glpi_computermodels'));
         $serie         = e($comp->fields['serial'] ?: 'N/D');
         $activo        = e($comp->fields['otherserial'] ?: 'N/D');
         $comentarios   = e($comp->fields['comment'] ?: 'Sin comentarios');
         $tipo          = e(ddn($comp->fields['computertypes_id'], 'glpi_computertypes', 'No especificado'));
         $estado_nombre = e(ddn($comp->fields['states_id'], 'glpi_states'));

         // CPU
         $cpu_name = 'No especificado';
         $cpu_freq = 'No especificada';
         foreach ((new Item_DeviceProcessor())->find(['items_id' => $comp->getID(), 'itemtype' => 'Computer'], ['id DESC'], 1) as $row) {
            $device = new DeviceProcessor();
            if ($device->getFromDB($row['deviceprocessors_id'])) {
               $mfr      = ddn($device->fields['manufacturers_id'], 'glpi_manufacturers', '');
               $cpu_name = e(trim($device->fields['designation'] ? $mfr . ' ' . $device->fields['designation'] : $cpu_name));
               $cpu_freq = !empty($device->fields['frequence'])
                  ? e(number_format($device->fields['frequence'] / 1000, 2) . ' GHz')
                  : $cpu_freq;
            }
         }

         // RAM
         $ram_parts = [];
         foreach ((new Item_DeviceMemory())->find(['items_id' => $comp->getID(), 'itemtype' => 'Computer', 'is_deleted' => 0]) as $row) {
            $device = new DeviceMemory();
            if ($device->getFromDB($row['devicememories_id']) && $device->fields['designation']) {
               $ram_parts[] = $device->fields['designation'];
            }
         }
         $ram_texto = e($ram_parts ? implode(' + ', $ram_parts) : 'No especificada');

         // SO
         $os_texto = 'No especificado';
         foreach ((new Item_OperatingSystem())->find(['items_id' => $comp->getID(), 'itemtype' => 'Computer', 'is_deleted' => 0], ['date_mod DESC'], 1) as $row) {
            $partes = [];
            if ($so = ddn($row['operatingsystems_id'] ?? 0, 'glpi_operatingsystems', ''))         $partes[] = $so;
            if ($v  = ddn($row['operatingsystemversions_id'] ?? 0, 'glpi_operatingsystemversions', '')) $partes[] = $v;
            if ($ed = ddn($row['operatingsystemeditions_id'] ?? 0, 'glpi_operatingsystemeditions', '')) $partes[] = $ed;
            if ($partes) $os_texto = e(implode(' ', $partes));
         }

         // Disco
         $disk_names = [];
         foreach ((new Item_DeviceHardDrive())->find(['items_id' => $comp->getID(), 'itemtype' => 'Computer', 'is_deleted' => 0]) as $row) {
            $device = new DeviceHardDrive();
            if ($device->getFromDB($row['deviceharddrives_id']) && $device->fields['designation']) {
               $disk_names[] = $device->fields['designation'];
            }
         }
         $disco = $disk_names ? e(implode(', ', array_unique($disk_names))) : 'No especificado';

         // Periféricos (monitores y accesorios)
         $dispositivos_html    = '';
         $printed_header_devs  = false;

         $result = $DB->request([
            'SELECT'     => ['glpi_monitors.serial', 'glpi_monitors.otherserial', 'glpi_monitors.states_id', 'glpi_monitors.manufacturers_id', 'glpi_monitors.monitormodels_id'],
            'FROM'       => 'glpi_assets_assets_peripheralassets',
            'INNER JOIN' => ['glpi_monitors' => ['ON' => ['glpi_assets_assets_peripheralassets' => 'items_id_peripheral', 'glpi_monitors' => 'id']]],
            'WHERE'      => ['glpi_assets_assets_peripheralassets.itemtype_asset' => 'Computer', 'glpi_assets_assets_peripheralassets.items_id_asset' => $comp->getID(), 'glpi_assets_assets_peripheralassets.itemtype_peripheral' => 'Monitor', 'glpi_assets_assets_peripheralassets.is_deleted' => 0, 'glpi_monitors.users_id' => $user_id],
         ]);
         foreach ($result as $row) {
            self::appendDevicesHeader($dispositivos_html, $printed_header_devs, $th_bg);
            $dispositivos_html .= "<tr style='background-color:{$td_bg};'>
<td width='20%'>Monitor</td>
<td width='20%'>" . e(ddn($row['manufacturers_id'], 'glpi_manufacturers')) . "</td>
<td width='20%'>" . e(ddn($row['monitormodels_id'], 'glpi_monitormodels')) . "</td>
<td width='20%'>" . e($row['serial'] ?: 'N/D') . " / " . e($row['otherserial'] ?: 'N/D') . "</td>
<td width='20%'>" . e(ddn($row['states_id'], 'glpi_states')) . "</td>
</tr>";
         }

         $result = $DB->request([
            'SELECT'     => ['glpi_peripherals.name', 'glpi_peripherals.serial', 'glpi_peripherals.otherserial', 'glpi_peripherals.states_id', 'glpi_peripherals.manufacturers_id', 'glpi_peripheraltypes.name AS tipo', 'glpi_peripheralmodels.name AS modelo'],
            'FROM'       => 'glpi_assets_assets_peripheralassets',
            'INNER JOIN' => [
               'glpi_peripherals'     => ['ON' => ['glpi_assets_assets_peripheralassets' => 'items_id_peripheral', 'glpi_peripherals' => 'id']],
               'glpi_peripheraltypes' => ['ON' => ['glpi_peripherals' => 'peripheraltypes_id', 'glpi_peripheraltypes' => 'id']],
            ],
            'LEFT JOIN'  => ['glpi_peripheralmodels' => ['ON' => ['glpi_peripherals' => 'peripheralmodels_id', 'glpi_peripheralmodels' => 'id']]],
            'WHERE'      => ['glpi_assets_assets_peripheralassets.itemtype_asset' => 'Computer', 'glpi_assets_assets_peripheralassets.items_id_asset' => $comp->getID(), 'glpi_assets_assets_peripheralassets.itemtype_peripheral' => 'Peripheral', 'glpi_assets_assets_peripheralassets.is_deleted' => 0, 'glpi_peripherals.users_id' => $user_id],
         ]);
         foreach ($result as $row) {
            self::appendDevicesHeader($dispositivos_html, $printed_header_devs, $th_bg);
            $dispositivos_html .= "<tr style='background-color:{$td_bg};'>
<td width='20%'>" . e($row['tipo'] ?? 'N/D') . "</td>
<td width='20%'>" . e(ddn($row['manufacturers_id'], 'glpi_manufacturers')) . "</td>
<td width='20%'>" . e(!empty($row['modelo']) ? $row['modelo'] : 'N/D') . "</td>
<td width='20%'>" . e($row['serial'] ?: 'N/D') . " / " . e($row['otherserial'] ?: 'N/D') . "</td>
<td width='20%'>" . e(ddn($row['states_id'], 'glpi_states')) . "</td>
</tr>";
         }

         $employee_line_html = $employee_line ? "<br>{$employee_line}" : '';

         // ── Plantillas editables ──
         $pc_vars = [
            '{nombre}'      => e($full_name),
            '{empresa}'     => $company_name,
            '{num_empleado}'=> $employee_number,
            '{activo}'      => $activo,
            '{serie}'       => $serie,
            '{marca}'       => $marca,
            '{modelo}'      => $modelo,
            '{tipo}'        => $tipo,
            '{estado}'      => $estado_nombre,
            '{fecha}'       => e(fechaATexto($_SESSION['glpi_currenttime'], $config['timezone'])),
            '{lugar}'       => $location,
         ];
         $pc_titulo = responsivasApplyTemplate($config['pc_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO', $pc_vars);
         $pc_intro  = responsivasApplyTemplate($config['pc_intro']  ?? '', $pc_vars);
         $pc_cuerpo = responsivasRenderTemplate(responsivasApplyTemplate($config['pc_cuerpo'] ?? '', $pc_vars));

         $html = <<<HTML
<h2 style="text-align:center;">{$pc_titulo}</h2>
<p style="text-align:justify;">{$pc_intro}</p>
<table border="1" cellpadding="3" cellspacing="0" width="100%">
<tr style="background-color:{$th_bg};"><td width="20%"><strong>Marca</strong></td><td width="20%"><strong>Modelo</strong></td><td width="20%"><strong>Serie</strong></td><td width="20%"><strong>Procesador</strong></td><td width="20%"><strong>Velocidad</strong></td></tr>
<tr style="background-color:{$td_bg};"><td>{$marca}</td><td>{$modelo}</td><td>{$serie}</td><td>{$cpu_name}</td><td>{$cpu_freq}</td></tr>
<tr style="background-color:{$th_bg};"><td><strong>Memoria RAM</strong></td><td><strong>SO</strong></td><td><strong>Almacenamiento</strong></td><td><strong>Tipo</strong></td><td><strong>Condición</strong></td></tr>
<tr style="background-color:{$td_bg};"><td>{$ram_texto}</td><td>{$os_texto}</td><td>{$disco}</td><td>{$tipo}</td><td>{$estado_nombre}</td></tr>
{$dispositivos_html}
<tr style="background-color:{$th_bg};"><td width="100%"><strong>Comentarios</strong></td></tr>
<tr style="background-color:{$td_bg};"><td width="100%">{$comentarios}</td></tr>
</table>
{$pc_cuerpo}
<table><tr><td height="30"></td></tr></table>
<div style="text-align:center;"><strong>_________________________________<br>{$full_name_safe}{$employee_line_html}</strong></div>
HTML;
         $pdf->writeHTML($html, true, false, true, false, '');
      }

      return [
         'pdf'      => $pdf,
         'filename' => self::makeFilename('Responsiva_Computo', $full_name),
      ];
   }

   /* =====================================================
    * PDF DE IMPRESORAS
    * ===================================================== */
   public static function buildPrinterPdf(int $user_id): array
   {
      global $CFG_GLPI;

      $config = Config::getConfigurationValues('plugin_responsivas');
      self::validateTemplates('pri', $config);

      $user = new User();
      if (!$user->getFromDB($user_id)) {
         throw new RuntimeException(__('Usuario no encontrado.'));
      }

      $printers = (new Printer())->find(['users_id' => $user_id, 'is_deleted' => 0]);
      if (empty($printers)) {
         throw new RuntimeException(__('El usuario no tiene equipos asignados.'));
      }

      $entity = new Entity();
      if (!$entity->getFromDB(Session::getActiveEntity())) {
         throw new RuntimeException(__('No se pudo obtener la entidad.'));
      }

      $location = e(implode(', ', array_filter([
         $entity->fields['town'],
         $entity->fields['state'],
         $entity->fields['country'],
      ])));

      $full_name       = $user->getFriendlyName();
      $employee_number = !empty($user->fields['registration_number']) ? $user->fields['registration_number'] : '';
      $show_employee   = (int)($config['show_employee_number'] ?? 0);
      $company_name    = e($config['company_name'] ?? '');
      $employee_line   = ($show_employee && $employee_number) ? "Empleado No: " . e($employee_number) . "<br>" : '';
      $creator         = self::getCreator();

      $th_bg = '#E6E6E6';
      $td_bg = '#FFFFFF';

      $pdf = new PluginResponsivasPDF('P', 'mm', 'LETTER');
      $pdf->setDocumentType('pri_font_size', 'pri');
      $pdf->fecha_header = fechaATexto($_SESSION['glpi_currenttime'], $config['timezone']);
      $pdf->location     = $location;
      $pdf->SetCreator('GLPI');
      $pdf->SetAuthor($creator);
      $pdf->SetTitle('Responsiva Impresora - ' . $full_name);
      $pdf->SetPDFVersion('1.4');
      $pdf->SetSubject('Responsiva de impresora');
      $pdf->SetKeywords('responsiva, impresora, activos, TI');
      $pdf->SetMargins(15, 25, 15);
      $pdf->SetAutoPageBreak(true, 40);
      $pdf->SetPrintHeader(true);
      $pdf->SetPrintFooter(true);
      $pdf->setCompression(true);
      $pdf->setFontSubsetting(true);
      $pdf->SetProtection(['copy', 'modify'], '', null);
      $pdf->SetFont(Config::getConfigurationValue('core', 'pdffont'), '', (int)$config['pri_font_size']);
      $pdf->AddPage();

      $i          = 0;
      $full_safe  = e($full_name);

      // ── Plantillas editables ──
      $pri_base_vars = [
         '{nombre}'       => e($full_name),
         '{empresa}'      => $company_name,
         '{num_empleado}' => e($employee_number),
         '{fecha}'        => e(fechaATexto($_SESSION['glpi_currenttime'], $config['timezone'])),
         '{lugar}'        => $location,
      ];

      foreach ($printers as $printer) {
         if ($i > 0) {
            $pdf->AddPage();
         }
         $page      = $pdf->getPage();
         $asset_url = $CFG_GLPI['url_base'] . '/front/printer.form.php?id=' . (int)$printer['id'];
         $pdf->setQrForPage($page, $asset_url);

         $marca         = e(ddn($printer['manufacturers_id'] ?? 0, 'glpi_manufacturers', 'No especificada'));
         $modelo        = e(ddn($printer['printermodels_id'] ?? 0, 'glpi_printermodels', 'No especificado'));
         $tipo          = e(ddn($printer['printertypes_id'] ?? 0, 'glpi_printertypes', 'No especificado'));
         $estado_nombre = e(ddn($printer['states_id'] ?? 0, 'glpi_states'));
         $serie         = e(!empty($printer['serial'])      ? $printer['serial']      : 'N/D');
         $activo        = e(!empty($printer['otherserial']) ? $printer['otherserial'] : 'N/D');
         $comentarios   = e(!empty($printer['comment'])     ? $printer['comment']     : 'Sin comentarios');

         $pri_vars = array_merge($pri_base_vars, [
            '{activo}'  => $activo,
            '{serie}'   => $serie,
            '{marca}'   => $marca,
            '{modelo}'  => $modelo,
            '{tipo}'    => $tipo,
            '{estado}'  => $estado_nombre,
         ]);
         $pri_titulo = responsivasApplyTemplate($config['pri_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO', $pri_vars);
         $pri_intro  = responsivasApplyTemplate($config['pri_intro']  ?? '', $pri_vars);
         $pri_cuerpo = responsivasRenderTemplate(responsivasApplyTemplate($config['pri_cuerpo'] ?? '', $pri_vars));

         $html = <<<HTML
<h2 style="text-align:center;">{$pri_titulo}</h2>
<p style="text-align:justify;">{$pri_intro}</p>
<table border="1" cellpadding="6" cellspacing="0" width="100%">
  <tr style="background-color:{$th_bg};">
    <td width="20%"><strong>Marca</strong></td>
    <td width="20%"><strong>Modelo</strong></td>
    <td width="20%"><strong>Serie</strong></td>
    <td width="20%"><strong>Tipo</strong></td>
    <td width="20%"><strong>Condición</strong></td>
  </tr>
  <tr style="background-color:{$td_bg};">
    <td>{$marca}</td>
    <td>{$modelo}</td>
    <td>{$serie}</td>
    <td>{$tipo}</td>
    <td>{$estado_nombre}</td>
  </tr>
  <tr style="background-color:{$th_bg};">
    <td width="100%"><strong>Comentarios</strong></td>
  </tr>
  <tr style="background-color:{$td_bg};">
    <td width="100%">{$comentarios}</td>
  </tr>
</table>
{$pri_cuerpo}
<table><tr><td height="40"></td></tr></table>
<div style="text-align:center;">
<strong>
_________________________________<br>
{$full_safe}<br>
{$employee_line}
</strong>
</div>
HTML;
         $pdf->writeHTML($html, true, false, true, false, '');
         $i++;
      }

      return [
         'pdf'      => $pdf,
         'filename' => self::makeFilename('Responsiva_Impresora', $full_name),
      ];
   }

   /* =====================================================
    * PDF DE TELÉFONOS (COMODATOS)
    * ===================================================== */
   public static function buildPhonePdf(int $user_id): array
   {
      global $DB, $CFG_GLPI;

      $config = Config::getConfigurationValues('plugin_responsivas');
      self::validateTemplates('pho', $config);

      $user = new User();
      if (!$user->getFromDB($user_id)) {
         throw new RuntimeException(__('Usuario no encontrado.'));
      }

      $cellphone_type_id = (int)($config['cellphone_type_id'] ?? 0);
      if ($cellphone_type_id <= 0) {
         throw new RuntimeException(__('No está configurado el tipo de teléfono para comodatos en el plugin.'));
      }

      $phones = (new Phone())->find([
         'users_id'      => $user_id,
         'is_deleted'    => 0,
         'phonetypes_id' => $cellphone_type_id,
      ]);
      if (empty($phones)) {
         throw new RuntimeException(__('El usuario no tiene teléfonos del tipo configurado asignados.'));
      }

      $entity = new Entity();
      if (!$entity->getFromDB(Session::getActiveEntity())) {
         throw new RuntimeException(__('No se pudo obtener la entidad.'));
      }

      $address  = e($entity->fields['address']  ?? '');
      $postcode = e($entity->fields['postcode'] ?? '');
      $location = e(implode(', ', array_filter([
         $entity->fields['town'],
         $entity->fields['state'],
         $entity->fields['country'],
      ])));

      $full_name       = $user->getFriendlyName();
      $employee_number = $user->fields['registration_number'] ?? '';
      $user_mobile     = !empty($user->fields['mobile']) ? $user->fields['mobile'] : 'N/D';

      $testigo1_id      = (int)($config['testigo_1']     ?? 0);
      $testigo2_id      = (int)($config['testigo_2']     ?? 0);
      $representante_id = (int)($config['representante'] ?? 0);

      if ($testigo1_id <= 0 || $testigo2_id <= 0) {
         throw new RuntimeException(__('Debe configurar Testigo 1 y Testigo 2 en la configuración del plugin.'));
      }
      if ($representante_id <= 0) {
         throw new RuntimeException(__('Debe configurar representante legal en la configuración del plugin.'));
      }

      $testigo1_nombre      = nombreUsuario($testigo1_id);
      $testigo2_nombre      = nombreUsuario($testigo2_id);
      $representante_nombre = nombreUsuario($representante_id);

      if (!$testigo1_nombre || !$testigo2_nombre) {
         throw new RuntimeException(__('Uno o ambos testigos configurados no son válidos o están inactivos.'));
      }
      if (!$representante_nombre) {
         throw new RuntimeException(__('El representante legal no es válido o está inactivo.'));
      }

      $show_employee = (int)($config['show_employee_number'] ?? 1);
      $company_name  = e($config['company_name'] ?? '');
      $emp_safe      = e($employee_number);
      $employee_line = ($show_employee && !empty($emp_safe)) ? "Empleado No: {$emp_safe}<br>" : '';

      // Pre-validar precios ANTES de crear el PDF
      foreach ($phones as $phone) {
         $infocoms_check = (new Infocom())->find(['itemtype' => 'Phone', 'items_id' => (int)$phone['id']], [], 1);
         $precio_check   = 0.0;
         if (!empty($infocoms_check)) {
            $ic = reset($infocoms_check);
            if (isset($ic['value']) && is_numeric($ic['value'])) {
               $precio_check = (float)$ic['value'];
            }
         }
         if ($precio_check <= 0) {
            $nombre_tel    = trim(ddn($phone['manufacturers_id'] ?? 0, 'glpi_manufacturers', '') . ' ' . ddn($phone['phonemodels_id'] ?? 0, 'glpi_phonemodels', ''));
            $nombre_activo = trim($phone['name'] ?? '');
            $identificador = $nombre_activo !== '' ? $nombre_activo . ($nombre_tel !== '' ? " ({$nombre_tel})" : '') : ($nombre_tel ?: 'IMEI: ' . ($phone['serial'] ?? 'N/D'));
            throw new RuntimeException(sprintf(
               __('El teléfono "%s" no tiene precio de compra. Agrégalo en Gestión → Información administrativa y financiera → Precio de compra.'),
               $identificador
            ));
         }
      }

      $currency   = !empty($config['currency']) ? $config['currency'] : '$';
      $dt         = new DateTime('now', new DateTimeZone($config['timezone']));
      $hora_texto = $dt->format('H') . ':00';
      $fecha_texto = fechaATexto($_SESSION['glpi_currenttime'], $config['timezone']);
      $creator    = self::getCreator();

      $pdf = new PluginResponsivasPDF('P', 'mm', 'LETTER');
      $pdf->setDocumentType('pho_font_size', 'pho');
      $pdf->fecha_header = $fecha_texto;
      $pdf->location     = $location;
      $pdf->SetCreator('GLPI');
      $pdf->SetAuthor($creator);
      $pdf->SetTitle('Comodato Teléfono - ' . $full_name);
      $pdf->SetPDFVersion('1.4');
      $pdf->SetSubject('Comodato de teléfono');
      $pdf->SetKeywords('comodato, teléfono, activos, TI');
      $pdf->SetMargins(15, 25, 15);
      $pdf->SetAutoPageBreak(true, 25);
      $pdf->SetPrintHeader(true);
      $pdf->SetPrintFooter(true);
      $pdf->setCompression(true);
      $pdf->setFontSubsetting(true);
      $pdf->SetProtection(['copy', 'modify'], '', null);
      $pdf->SetFont(Config::getConfigurationValue('core', 'pdffont'), '', (int)($config['pho_font_size']));

      $full_name_safe = e($full_name);

      foreach ($phones as $phone) {
         $pdf->AddPage();
         $page      = $pdf->getPage();
         $asset_url = $CFG_GLPI['url_base'] . '/front/phone.form.php?id=' . (int)$phone['id'];
         $pdf->setQrForPage($page, $asset_url);

         $marca  = ddn($phone['manufacturers_id'] ?? 0, 'glpi_manufacturers', 'No especificada');
         $modelo = ddn($phone['phonemodels_id'] ?? 0, 'glpi_phonemodels', 'No especificado');
         $imei   = $phone['serial'] ?? 'N/D';
         $activo = $phone['otherserial'] ?? 'N/D';
         $serie  = $phone['uuid'] ?? 'N/D';
         $linea  = $user_mobile;
         $estado = ddn($phone['states_id'] ?? 0, 'glpi_states');

         // Infocoms
         $infocoms          = (new Infocom())->find(['itemtype' => 'Phone', 'items_id' => (int)$phone['id']], [], 1);
         $precio_compra_num = 0.0;
         $precio_compra     = 'N/D';
         $factura           = 'N/D';
         $fecha_compra      = 'N/D';
         $proveedor         = 'N/D';

         if (!empty($infocoms)) {
            $infocom = reset($infocoms);
            if (isset($infocom['value']) && is_numeric($infocom['value']) && (float)$infocom['value'] > 0) {
               $precio_compra_num = (float)$infocom['value'];
               $precio_compra     = $currency . number_format($precio_compra_num, 2, '.', ',');
            }
            if (!empty(trim($infocom['bill'] ?? ''))) {
               $factura = trim($infocom['bill']);
            }
            if (!empty($infocom['buy_date']) && $infocom['buy_date'] !== '0000-00-00') {
               $fecha_compra = fechaATexto($infocom['buy_date'], $config['timezone']);
            }
            $proveedor = ddn($infocom['suppliers_id'] ?? 0, 'glpi_suppliers');
         }

         // RAM
         $ram_parts = [];
         foreach ((new Item_DeviceMemory())->find(['items_id' => (int)$phone['id'], 'itemtype' => 'Phone', 'is_deleted' => 0]) as $mem) {
            if (!empty($mem['devicememories_id'])) {
               $des = ddn((int)$mem['devicememories_id'], 'glpi_devicememories');
               if (!empty($des)) $ram_parts[] = $des;
            }
         }
         $ram_texto = !empty($ram_parts) ? implode(' + ', $ram_parts) : 'No especificada';

         // Disco
         $disco    = 'No especificado';
         $total_mb = 0;
         $nombres  = [];
         foreach ((new Item_DeviceHardDrive())->find(['items_id' => (int)$phone['id'], 'itemtype' => 'Phone', 'is_deleted' => 0]) as $row) {
            if (!empty($row['capacity']) && is_numeric($row['capacity'])) $total_mb += (int)$row['capacity'];
            if (!empty($row['deviceharddrives_id'])) {
               $n = ddn((int)$row['deviceharddrives_id'], 'glpi_deviceharddrives');
               if (!empty($n)) $nombres[] = $n;
            }
         }
         if ($total_mb > 0 || !empty($nombres)) {
            $partes = [];
            if (!empty($nombres)) $partes[] = implode(', ', array_unique($nombres));
            if ($total_mb > 0)    $partes[] = round($total_mb / 1024) . ' GB';
            $disco = implode(' - ', $partes);
         }

         // Escape seguro
         $marca    = e($marca);
         $modelo   = e($modelo);
         $serie    = e($serie);
         $activo   = e($activo);
         $imei     = e($imei);
         $linea    = e($linea);
         $estado   = e($estado);

         // ── Plantillas editables ──
         $clausula_vida_util_text = '';
         if ($factura !== 'N/D' && $proveedor !== 'N/D') {
            $partes_cu = [];
            if ($fecha_compra !== 'N/D') $partes_cu[] = 'contados a partir del ' . e($fecha_compra);
            $partes_cu[]              = 'con base en la factura ' . e($factura) . ' emitida por ' . e($proveedor);
            $clausula_vida_util_text  = 'Se establece como <strong>vida útil</strong> un periodo de 24 meses ' . implode(', ', $partes_cu) . '.';
         } else {
            $clausula_vida_util_text = 'Se establece como <strong>vida útil</strong> un periodo de 24 meses desde la fecha de asignación.';
         }

         $pho_vars = [
            '{nombre}'            => e($full_name),
            '{empresa}'           => $company_name,
            '{num_empleado}'      => e($employee_number),
            '{activo}'            => $activo,
            '{serie_uuid}'        => $serie,
            '{imei}'              => $imei,
            '{marca}'             => $marca,
            '{modelo}'            => $modelo,
            '{estado}'            => $estado,
            '{precio}'            => e($precio_compra),
            '{linea}'             => $linea,
            '{ram}'               => e($ram_texto),
            '{almacenamiento}'    => e($disco),
            '{fecha}'             => e($fecha_texto),
            '{hora}'              => e($hora_texto),
            '{lugar}'             => $location,
            '{direccion}'         => $address,
            '{cp}'                => $postcode,
            '{representante}'     => $representante_nombre,
            '{testigo1}'          => $testigo1_nombre,
            '{testigo2}'          => $testigo2_nombre,
            '{clausula_vida_util}'=> $clausula_vida_util_text,
         ];
         $pho_titulo    = responsivasApplyTemplate($config['pho_titulo']    ?? 'CONTRATO DE COMODATO', $pho_vars);
         $pho_apertura  = responsivasApplyTemplate($config['pho_apertura']  ?? '', $pho_vars);
         $pho_clausulas = responsivasRenderTemplate(responsivasApplyTemplate($config['pho_clausulas'] ?? '', $pho_vars));
         $pho_testigos  = responsivasApplyTemplate($config['pho_testigos']  ?? '', $pho_vars);

         $html = <<<HTML
<p style="text-align:center;"><strong>{$pho_titulo}</strong></p>
<p style="text-align:justify; line-height:1.15;">{$pho_apertura}</p>
<p style="text-align:center;"><strong>CLÁUSULAS</strong></p>
{$pho_clausulas}
<p style="text-align:justify; line-height:1.15;">{$pho_testigos}</p>
<br>
<table width="100%" style="text-align:center;">
<tr>
  <td width="50%"><strong>COMODANTE</strong><br><br>_______________________________<br>{$representante_nombre}</td>
  <td width="50%"><strong>COMODATARIO</strong><br><br>_______________________________<br>{$full_name_safe}<br>{$employee_line}</td>
</tr>
</table>
<br>
<table width="100%" style="text-align:center;">
<tr>
  <td width="50%"><strong>TESTIGO</strong><br><br>_______________________________<br>{$testigo1_nombre}</td>
  <td width="50%"><strong>TESTIGO</strong><br><br>_______________________________<br>{$testigo2_nombre}</td>
</tr>
</table>
HTML;
         $pdf->writeHTML($html, true, false, true, false, '');
      }

      return [
         'pdf'      => $pdf,
         'filename' => self::makeFilename('Comodato_Celular', $full_name),
      ];
   }
}
