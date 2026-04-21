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
      $l = self::lbl();
      $html .= <<<HTML
<tr style="background-color:{$th_bg};">
  <td width="20%"><strong>{$l['device']}</strong></td>
  <td width="20%"><strong>{$l['brand']}</strong></td>
  <td width="20%"><strong>{$l['model']}</strong></td>
  <td width="20%"><strong>{$l['serial_asset']}</strong></td>
  <td width="20%"><strong>{$l['condition']}</strong></td>
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
         'pc_titulo'      => __('Title (Computer)', 'responsivas'),
         'pc_intro'       => __('Introduction (Computer)', 'responsivas'),
         'pc_cuerpo'      => __('Body / Clauses (Computer)', 'responsivas'),
         'pri_titulo'     => __('Title (Printer)', 'responsivas'),
         'pri_intro'      => __('Introduction (Printer)', 'responsivas'),
         'pri_cuerpo'     => __('Body / Clauses (Printer)', 'responsivas'),
         'pho_titulo'     => __('Title (Phone)', 'responsivas'),
         'pho_apertura'   => __('Opening paragraph (Phone)', 'responsivas'),
         'pho_clausulas'  => __('Clauses (Phone)', 'responsivas'),
         'pho_testigos'   => __('Witnesses paragraph (Phone)', 'responsivas'),
      ];

      $missing = [];
      foreach ($required[$type] ?? [] as $key) {
         if (empty(trim($config[$key] ?? ''))) {
            $missing[] = $labels[$key] ?? $key;
         }
      }

      if (!empty($missing)) {
         throw new RuntimeException(sprintf(
            __('The document template has empty fields. Complete the following fields in Configuration → Responsivas: %s', 'responsivas'),
            implode(', ', $missing)
         ));
      }
   }


   /* =====================================================
    * ETIQUETAS TRADUCIBLES DEL DOCUMENTO
    * Centraliza todos los strings de cabeceras de tablas
    * y etiquetas de firma que aparecen en los PDFs.
    * ===================================================== */
   private static function lbl(): array
   {
      static $cache = [];
      $locale = $_SESSION['glpilanguage'] ?? 'en';
      if (isset($cache[$locale])) return $cache[$locale];
      return $cache[$locale] = [
         // Table headers
         'brand'       => __('Brand',        'responsivas'),
         'model'       => __('Model',        'responsivas'),
         'serial'      => __('Serial',       'responsivas'),
         'processor'   => __('Processor',    'responsivas'),
         'speed'       => __('Speed',        'responsivas'),
         'ram'         => __('RAM',          'responsivas'),
         'os'          => __('OS',           'responsivas'),
         'storage'     => __('Storage',      'responsivas'),
         'type'        => __('Type',         'responsivas'),
         'condition'   => __('Condition',    'responsivas'),
         'comments'    => __('Comments',     'responsivas'),
         'device'      => __('Device',       'responsivas'),
         'serial_asset'=> __('Serial / Asset','responsivas'),
         'asset_no'    => __('Asset No.',    'responsivas'),
         'serial_uuid' => __('Serial/UUID',  'responsivas'),
         'imei'        => __('IMEI',         'responsivas'),
         'line'        => __('Line',         'responsivas'),
         'price'       => __('Price',        'responsivas'),
         // Signature labels
         'clauses'     => __('CLAUSES',      'responsivas'),
         'lender'      => __('LENDER',       'responsivas'),
         'borrower'    => __('BORROWER',     'responsivas'),
         'witness'     => __('WITNESS',      'responsivas'),
         // Fallbacks
         'not_specified' => __('Not specified', 'responsivas'),
         'no_comments'   => __('No comments',   'responsivas'),
         'na'            => __('N/A',            'responsivas'),
         'in_use'        => __('In use',         'responsivas'),
         'employee_no'   => __('Employee No.: ', 'responsivas'),
      ];
   }

   public static function buildComputerPdf(int $user_id): array
   {
      global $DB, $CFG_GLPI;

      $config = Config::getConfigurationValues('plugin_responsivas');
      self::validateTemplates('pc', $config);

      $user = new User();
      if (!$user->getFromDB($user_id)) {
         throw new RuntimeException(__('User not found.', 'responsivas'));
      }

      $computers = [];
      foreach ((new Computer())->find(['users_id' => $user_id, 'is_deleted' => 0]) as $row) {
         $comp         = new Computer();
         $comp->fields = $row;
         $computers[]  = $comp;
      }
      if (empty($computers)) {
         throw new RuntimeException(__('The user has no assigned equipment.', 'responsivas'));
      }

      $entity = new Entity();
      if (!$entity->getFromDB(Session::getActiveEntity())) {
         throw new RuntimeException(__('Could not retrieve the entity.', 'responsivas'));
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
      $employee_line   = ($show_employee && $employee_number) ? (__("Employee No.: ", "responsivas") . $employee_number) : '';

      $th_bg = '#E6E6E6';
      $td_bg = '#FFFFFF';

      $show_both_sigs_pc  = (int)($config['pc_show_comodato_sigs'] ?? 0) === 1;
      $representante_pc   = '';
      if ($show_both_sigs_pc) {
         $rep_id = (int)($config['representante'] ?? 0);
         $representante_pc = $rep_id > 0 ? (nombreUsuario($rep_id) ?? '') : '';
      }

      $pdf = self::makePdf('pc',
         __('Computer Responsibility - ', 'responsivas') . $full_name,
         'Responsiva de computadora',
         'responsiva, computadora, activos, TI',
         $location,
         fechaATexto($_SESSION['glpi_currenttime'], $config['timezone']),
         $config, 30.0
      );

      $full_name_safe = e($full_name);

      foreach ($computers as $comp) {
         $pdf->AddPage();
         $page = $pdf->getPage();
         $pdf->setQrForPage($page, $CFG_GLPI['url_base'] . '/front/computer.form.php?id=' . $comp->getID());

         $marca         = e(ddn($comp->fields['manufacturers_id'], 'glpi_manufacturers'));
         $modelo        = e(ddn($comp->fields['computermodels_id'], 'glpi_computermodels'));
         $serie         = e($comp->fields['serial'] ?: 'N/A');
         $activo        = e($comp->fields['otherserial'] ?: 'N/A');
         $comentarios   = e($comp->fields['comment'] ?: __('No comments', 'responsivas'));
         $tipo          = e(ddn($comp->fields['computertypes_id'], 'glpi_computertypes', __('Not specified', 'responsivas')));
         $estado_nombre = e(ddn($comp->fields['states_id'], 'glpi_states'));

         // CPU
         $cpu_name = __('Not specified', 'responsivas');
         $cpu_freq = __('Not specified', 'responsivas');
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
         $ram_texto = e($ram_parts ? implode(' + ', $ram_parts) : __('Not specified', 'responsivas'));

         // SO
         $os_texto = __('Not specified', 'responsivas');
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
         $disco = $disk_names ? e(implode(', ', array_unique($disk_names))) : __('Not specified', 'responsivas');

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
<td width='20%'>" . e($row['serial'] ?: 'N/A') . " / " . e($row['otherserial'] ?: 'N/A') . "</td>
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
<td width='20%'>" . e($row['tipo'] ?? 'N/A') . "</td>
<td width='20%'>" . e(ddn($row['manufacturers_id'], 'glpi_manufacturers')) . "</td>
<td width='20%'>" . e(!empty($row['modelo']) ? $row['modelo'] : 'N/A') . "</td>
<td width='20%'>" . e($row['serial'] ?: 'N/A') . " / " . e($row['otherserial'] ?: 'N/A') . "</td>
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

         $pdf->writeHTML(self::renderPcPage(
            $pc_titulo, $pc_intro, $pc_cuerpo,
            $marca, $modelo, $serie, $cpu_name, $cpu_freq,
            $ram_texto, $os_texto, $disco, $tipo, $estado_nombre,
            $comentarios, $dispositivos_html,
            $full_name_safe, $employee_line_html,
            $th_bg, $td_bg,
            $show_both_sigs_pc, $representante_pc
         ), true, false, true, false, '');
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
         throw new RuntimeException(__('User not found.', 'responsivas'));
      }

      $printers = (new Printer())->find(['users_id' => $user_id, 'is_deleted' => 0]);
      if (empty($printers)) {
         throw new RuntimeException(__('The user has no assigned equipment.', 'responsivas'));
      }

      $entity = new Entity();
      if (!$entity->getFromDB(Session::getActiveEntity())) {
         throw new RuntimeException(__('Could not retrieve the entity.', 'responsivas'));
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
      $employee_line   = ($show_employee && $employee_number) ? (__("Employee No.: ", "responsivas") . e($employee_number)) : '';

      $th_bg = '#E6E6E6';
      $td_bg = '#FFFFFF';

      $show_both_sigs_pri = (int)($config['pri_show_comodato_sigs'] ?? 0) === 1;
      $representante_pri  = '';
      if ($show_both_sigs_pri) {
         $rep_id = (int)($config['representante'] ?? 0);
         $representante_pri = $rep_id > 0 ? (nombreUsuario($rep_id) ?? '') : '';
      }

      $pdf = self::makePdf('pri',
         __('Printer Responsibility - ', 'responsivas') . $full_name,
         'Responsiva de impresora',
         'responsiva, impresora, activos, TI',
         $location,
         fechaATexto($_SESSION['glpi_currenttime'], $config['timezone']),
         $config, 40.0
      );
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

         $marca         = e(ddn($printer['manufacturers_id'] ?? 0, 'glpi_manufacturers', __('Not specified', 'responsivas')));
         $modelo        = e(ddn($printer['printermodels_id'] ?? 0, 'glpi_printermodels', __('Not specified', 'responsivas')));
         $tipo          = e(ddn($printer['printertypes_id'] ?? 0, 'glpi_printertypes', __('Not specified', 'responsivas')));
         $estado_nombre = e(ddn($printer['states_id'] ?? 0, 'glpi_states'));
         $serie         = e(!empty($printer['serial'])      ? $printer['serial']      : 'N/A');
         $activo        = e(!empty($printer['otherserial']) ? $printer['otherserial'] : 'N/A');
         $comentarios   = e(!empty($printer['comment'])     ? $printer['comment']     : __('No comments', 'responsivas'));

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

         $pdf->writeHTML(self::renderPriPage(
            $pri_titulo, $pri_intro, $pri_cuerpo,
            $marca, $modelo, $serie, $tipo, $estado_nombre, $comentarios,
            $full_safe, $employee_line,
            $th_bg, $td_bg,
            $show_both_sigs_pri, $representante_pri
         ), true, false, true, false, '');
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
         throw new RuntimeException(__('User not found.', 'responsivas'));
      }

      $cellphone_type_id = (int)($config['cellphone_type_id'] ?? 0);
      if ($cellphone_type_id <= 0) {
         throw new RuntimeException(__('The phone type for loan agreements is not configured in the plugin.', 'responsivas'));
      }

      $phones = (new Phone())->find([
         'users_id'      => $user_id,
         'is_deleted'    => 0,
         'phonetypes_id' => $cellphone_type_id,
      ]);
      if (empty($phones)) {
         throw new RuntimeException(__('The user has no phones of the configured type assigned.', 'responsivas'));
      }

      $entity = new Entity();
      if (!$entity->getFromDB(Session::getActiveEntity())) {
         throw new RuntimeException(__('Could not retrieve the entity.', 'responsivas'));
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
      $user_mobile     = !empty($user->fields['mobile']) ? $user->fields['mobile'] : 'N/A';

      $testigo1_id      = (int)($config['testigo_1']     ?? 0);
      $testigo2_id      = (int)($config['testigo_2']     ?? 0);
      $representante_id = (int)($config['representante'] ?? 0);

      if ($testigo1_id <= 0 || $testigo2_id <= 0) {
         throw new RuntimeException(__('You must configure Witness 1 and Witness 2 in the plugin settings.', 'responsivas'));
      }
      if ($representante_id <= 0) {
         throw new RuntimeException(__('You must configure the legal representative in the plugin settings.', 'responsivas'));
      }

      $testigo1_nombre      = nombreUsuario($testigo1_id);
      $testigo2_nombre      = nombreUsuario($testigo2_id);
      $representante_nombre = nombreUsuario($representante_id);

      if (!$testigo1_nombre || !$testigo2_nombre) {
         throw new RuntimeException(__('One or both configured witnesses are invalid or inactive.', 'responsivas'));
      }
      if (!$representante_nombre) {
         throw new RuntimeException(__('The legal representative is invalid or inactive.', 'responsivas'));
      }

      $show_employee = (int)($config['show_employee_number'] ?? 1);
      $company_name  = e($config['company_name'] ?? '');
      $emp_safe      = e($employee_number);
      $employee_line = ($show_employee && !empty($emp_safe)) ? (__("Employee No.: ", "responsivas") . $emp_safe) : '';

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
            $identificador = $nombre_activo !== '' ? $nombre_activo . ($nombre_tel !== '' ? " ({$nombre_tel})" : '') : ($nombre_tel ?: 'IMEI: ' . ($phone['serial'] ?? 'N/A'));
            throw new RuntimeException(sprintf(
               __('Phone "%s" has no purchase price. Add it in Management → Administrative and financial information → Purchase price.', 'responsivas'),
               $identificador
            ));
         }
      }

      $currency    = !empty($config['currency']) ? $config['currency'] : '$';
      $dt          = new DateTime('now', new DateTimeZone($config['timezone']));
      $hora_texto  = $dt->format('H') . ':00';
      $fecha_texto = fechaATexto($_SESSION['glpi_currenttime'], $config['timezone']);

      $pdf = self::makePdf('pho',
         __('Phone Loan - ', 'responsivas') . $full_name,
         'Comodato de teléfono',
         'comodato, teléfono, activos, TI',
         $location,
         $fecha_texto,
         $config, 25.0
      );

      $full_name_safe = e($full_name);

      foreach ($phones as $phone) {
         $pdf->AddPage();
         $page      = $pdf->getPage();
         $asset_url = $CFG_GLPI['url_base'] . '/front/phone.form.php?id=' . (int)$phone['id'];
         $pdf->setQrForPage($page, $asset_url);

         $marca  = ddn($phone['manufacturers_id'] ?? 0, 'glpi_manufacturers', __('Not specified', 'responsivas'));
         $modelo = ddn($phone['phonemodels_id'] ?? 0, 'glpi_phonemodels', __('Not specified', 'responsivas'));
         $imei   = $phone['serial'] ?? 'N/A';
         $activo = $phone['otherserial'] ?? 'N/A';
         $serie  = $phone['uuid'] ?? 'N/A';
         $linea  = $user_mobile;
         $estado = ddn($phone['states_id'] ?? 0, 'glpi_states');

         // Infocoms
         $infocoms          = (new Infocom())->find(['itemtype' => 'Phone', 'items_id' => (int)$phone['id']], [], 1);
         $precio_compra_num = 0.0;
         $precio_compra     = 'N/A';
         $factura           = 'N/A';
         $fecha_compra      = 'N/A';
         $proveedor         = 'N/A';

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
         $ram_texto = !empty($ram_parts) ? implode(' + ', $ram_parts) : __('Not specified', 'responsivas');

         // Disco
         $disco    = __('Not specified', 'responsivas');
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
         // Cláusula de vida útil — usa plantilla configurable, cae a texto predeterminado si vacío
         $vu_vars = [
            '{fecha_compra}' => $fecha_compra !== 'N/A' ? e($fecha_compra) : '',
            '{factura}'      => e($factura),
            '{proveedor}'    => e($proveedor),
         ];
         if ($factura !== 'N/A' && $proveedor !== 'N/A') {
            $vu_tpl = trim($config['pho_vida_util_factura'] ?? '');
            if ($vu_tpl !== '') {
               $clausula_vida_util_text = responsivasApplyTemplate($vu_tpl, $vu_vars);
            } else {
               $partes_cu = [];
               if ($fecha_compra !== 'N/A') $partes_cu[] = 'contados a partir del ' . e($fecha_compra);
               $partes_cu[]             = 'con base en la factura ' . e($factura) . ' emitida por ' . e($proveedor);
               $clausula_vida_util_text = 'Se establece como <strong>vida útil</strong> un periodo de 24 meses ' . implode(', ', $partes_cu) . '.';
            }
         } else {
            $vu_tpl = trim($config['pho_vida_util_sin'] ?? '');
            $clausula_vida_util_text = $vu_tpl !== ''
               ? $vu_tpl
               : 'Se establece como <strong>vida útil</strong> un periodo de 24 meses desde la fecha de asignación.';
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
         $pho_titulo    = responsivasApplyTemplate($config['pho_titulo'] ?? 'CONTRATO DE COMODATO', $pho_vars);
         $pho_apertura  = responsivasApplyTemplate($config['pho_apertura']  ?? '', $pho_vars);
         $pho_clausulas = responsivasRenderTemplate(responsivasApplyTemplate($config['pho_clausulas'] ?? '', $pho_vars));
         $pho_testigos  = responsivasApplyTemplate($config['pho_testigos']  ?? '', $pho_vars);

         $pdf->writeHTML(self::renderPhoPage(
            $pho_titulo, $pho_apertura, $pho_clausulas, $pho_testigos,
            $representante_nombre, $full_name_safe, $employee_line,
            $testigo1_nombre, $testigo2_nombre
         ), true, false, true, false, '');
      }

      return [
         'pdf'      => $pdf,
         'filename' => self::makeFilename('Comodato_Celular', $full_name),
      ];
   }
   /* =====================================================
    * VISTA PREVIA con marca de agua
    * Intenta usar activos reales del admin; si no tiene,
    * construye datos demo para que la plantilla se vea real.
    * ===================================================== */

   /* =====================================================
    * MÉTODOS COMPARTIDOS DE RENDERIZADO HTML
    * Usados tanto por los builds reales como por el demo.
    * Cualquier cambio en el layout se aplica a ambos automáticamente.
    * ===================================================== */


   /* =====================================================
    * FACTORY COMPARTIDO
    * Crea y configura un PluginResponsivasPDF con todos
    * los ajustes base. Usado por builds reales y demo.
    * $page_break: margen inferior para auto page break.
    * ===================================================== */
   private static function makePdf(
      string $type,         // 'pc' | 'pri' | 'pho'
      string $title,
      string $subject,
      string $keywords,
      string $location,
      string $fecha_header,
      array  $config,
      float  $page_break = 30.0,
      bool   $watermark  = false
   ): PluginResponsivasPDF {
      $font_key = match ($type) { 'pc' => 'pc_font_size', 'pri' => 'pri_font_size', 'pho' => 'pho_font_size' };
      $creator  = self::getCreator();

      $pdf = new PluginResponsivasPDF('P', 'mm', 'LETTER');
      $pdf->setDocumentType($font_key, $type);
      $pdf->fecha_header   = $fecha_header;
      $pdf->location       = $location;
      $pdf->show_watermark    = $watermark;
      $wm_text                = trim($config['watermark_text'] ?? '');
      $pdf->watermark_text    = $wm_text !== '' ? $wm_text : __('PREVIEW', 'responsivas');
      $pdf->watermark_opacity = max(5, min(100, (int)($config['watermark_opacity'] ?? 25)));
      $pdf->SetCreator('GLPI');
      $pdf->SetAuthor($creator);
      $pdf->SetTitle($title);
      $pdf->SetPDFVersion('1.4');
      $pdf->SetSubject($subject);
      $pdf->SetKeywords($keywords);
      $pdf->SetMargins(15, 25, 15);
      $pdf->SetAutoPageBreak(true, $page_break);
      $pdf->SetPrintHeader(true);
      $pdf->SetPrintFooter(true);
      $pdf->setCompression((bool)($config['pdf_compression'] ?? 1));
      $pdf->setFontSubsetting(true);
      if ((int)($config['pdf_protection'] ?? 1) === 1) {
         $pdf->SetProtection(['copy', 'modify'], '', null);
      }
      $pdf->SetFont(Config::getConfigurationValue('core', 'pdffont'), '', (int)($config[$font_key] ?? 10));
      return $pdf;
   }

   private static function renderPcPage(
      string $titulo, string $intro, string $cuerpo,
      string $marca, string $modelo, string $serie,
      string $cpu_name, string $cpu_freq,
      string $ram, string $os, string $disco,
      string $tipo, string $estado, string $comentarios,
      string $dispositivos_html,
      string $full_name_safe, string $employee_line_html,
      string $th_bg, string $td_bg,
      bool   $show_both_sigs = false,
      string $representante = ''
   ): string {
      $l = self::lbl();

      $sig_block = $show_both_sigs
         ? '<br><br><br><table nobr="true" width="100%" style="text-align:center;">'
           . '<tr>'
           . '<td width="50%"><strong>' . $l['lender'] . '</strong><br><br>_______________________________<br>' . $representante . '</td>'
           . '<td width="50%"><strong>' . $l['borrower'] . '</strong><br><br>_______________________________<br>' . $full_name_safe . $employee_line_html . '</td>'
           . '</tr></table>'
         : '<br><br><br><table nobr="true" width="100%" style="text-align:center;">'
           . '<tr><td><strong>_________________________________<br>' . $full_name_safe . $employee_line_html . '</strong></td></tr>'
           . '</table>';

      return <<<HTML
<h2 style="text-align:center;">{$titulo}</h2>
<table nobr="true" width="100%"><tr><td style="text-align:justify;line-height:1.2;">{$intro}</td></tr></table>
<table border="1" cellpadding="3" cellspacing="0" width="100%">
<tr style="background-color:{$th_bg};"><td width="20%"><strong>{$l['brand']}</strong></td><td width="20%"><strong>{$l['model']}</strong></td><td width="20%"><strong>{$l['serial']}</strong></td><td width="20%"><strong>{$l['processor']}</strong></td><td width="20%"><strong>{$l['speed']}</strong></td></tr>
<tr style="background-color:{$td_bg};"><td>{$marca}</td><td>{$modelo}</td><td>{$serie}</td><td>{$cpu_name}</td><td>{$cpu_freq}</td></tr>
<tr style="background-color:{$th_bg};"><td><strong>{$l['ram']}</strong></td><td><strong>{$l['os']}</strong></td><td><strong>{$l['storage']}</strong></td><td><strong>{$l['type']}</strong></td><td><strong>{$l['condition']}</strong></td></tr>
<tr style="background-color:{$td_bg};"><td>{$ram}</td><td>{$os}</td><td>{$disco}</td><td>{$tipo}</td><td>{$estado}</td></tr>
{$dispositivos_html}
<tr style="background-color:{$th_bg};"><td width="100%"><strong>{$l['comments']}</strong></td></tr>
<tr style="background-color:{$td_bg};"><td width="100%">{$comentarios}</td></tr>
</table>
{$cuerpo}
{$sig_block}
HTML;
   }

   private static function renderPriPage(
      string $titulo, string $intro, string $cuerpo,
      string $marca, string $modelo, string $serie,
      string $tipo, string $estado, string $comentarios,
      string $full_safe, string $employee_line,
      string $th_bg, string $td_bg,
      bool   $show_both_sigs = false,
      string $representante = ''
   ): string {
      $l = self::lbl();

      $emp_sep   = $employee_line !== '' ? '<br>' . $employee_line : '';
      $sig_block = $show_both_sigs
         ? '<br><br><br><table nobr="true" width="100%" style="text-align:center;">'
           . '<tr>'
           . '<td width="50%"><strong>' . $l['lender'] . '</strong><br><br>_______________________________<br>' . $representante . '</td>'
           . '<td width="50%"><strong>' . $l['borrower'] . '</strong><br><br>_______________________________<br>' . $full_safe . $emp_sep . '</td>'
           . '</tr></table>'
         : '<br><br><br><table nobr="true" width="100%" style="text-align:center;">'
           . '<tr><td><strong>_________________________________<br>' . $full_safe . $emp_sep . '</strong></td></tr>'
           . '</table>';

      return <<<HTML
<h2 style="text-align:center;">{$titulo}</h2>
<table nobr="true" width="100%"><tr><td style="text-align:justify;line-height:1.2;">{$intro}</td></tr></table>
<table border="1" cellpadding="6" cellspacing="0" width="100%">
  <tr style="background-color:{$th_bg};">
    <td width="20%"><strong>{$l['brand']}</strong></td>
    <td width="20%"><strong>{$l['model']}</strong></td>
    <td width="20%"><strong>{$l['serial']}</strong></td>
    <td width="20%"><strong>{$l['type']}</strong></td>
    <td width="20%"><strong>{$l['condition']}</strong></td>
  </tr>
  <tr style="background-color:{$td_bg};">
    <td>{$marca}</td>
    <td>{$modelo}</td>
    <td>{$serie}</td>
    <td>{$tipo}</td>
    <td>{$estado}</td>
  </tr>
  <tr style="background-color:{$th_bg};">
    <td colspan="5"><strong>{$l['comments']}</strong></td>
  </tr>
  <tr style="background-color:{$td_bg};">
    <td colspan="5">{$comentarios}</td>
  </tr>
</table>
{$cuerpo}
{$sig_block}
HTML;
   }

   private static function renderPhoPage(
      string $titulo, string $apertura, string $clausulas, string $testigos,
      string $representante, string $full_name_safe, string $employee_line,
      string $testigo1, string $testigo2
   ): string {
      $l       = self::lbl();
      $emp_tag = $employee_line !== '' ? '<br>' . $employee_line : '';
      return <<<HTML
<p style="text-align:center;"><strong>{$titulo}</strong></p>
<table nobr="true" width="100%"><tr><td style="text-align:justify;line-height:1.15;">{$apertura}</td></tr></table>
<p style="text-align:center;"><strong>{$l['clauses']}</strong></p>
{$clausulas}
<table nobr="true" width="100%"><tr><td style="text-align:justify;line-height:1.15;">{$testigos}</td></tr></table>
<table nobr="true" width="100%" style="text-align:center;margin-top:10pt;">
<tr>
  <td width="50%"><strong>{$l['lender']}</strong><br><br>_______________________________<br>{$representante}</td>
  <td width="50%"><strong>{$l['borrower']}</strong><br><br>_______________________________<br>{$full_name_safe}{$emp_tag}</td>
</tr>
</table>
<table nobr="true" width="100%" style="text-align:center;margin-top:10pt;">
<tr>
  <td width="50%"><strong>{$l['witness']}</strong><br><br>_______________________________<br>{$testigo1}</td>
  <td width="50%"><strong>{$l['witness']}</strong><br><br>_______________________________<br>{$testigo2}</td>
</tr>
</table>
HTML;
   }

   public static function buildPreview(string $type, int $user_id, array $config): array
   {
      global $CFG_GLPI;

      // No validar plantillas aquí — si están vacías usamos demo igual
      $user = new User();
      if (!$user->getFromDB($user_id)) {
         throw new RuntimeException(__('User not found.', 'responsivas'));
      }

      // ── Intentar con activos reales ──────────────────────────────────
      // Primero comprobamos si el usuario tiene activos del tipo solicitado
      // antes de llamar a buildXxxPdf (que lanza excepción si no hay activos
      // pero también si la plantilla está vacía o faltan datos de entidad)
      $has_assets = match ($type) {
         'pc'  => count((new Computer())->find(['users_id' => $user_id, 'is_deleted' => 0])) > 0,
         'pri' => count((new Printer())->find(['users_id'  => $user_id, 'is_deleted' => 0])) > 0,
         'pho' => self::userHasPhones($user_id, $config),
      };

      if ($has_assets) {
         try {
            // Activar watermark ANTES de construir para que Header() lo dibuje
            // en cada página conforme se agregan
            $method = match ($type) {
               'pc'  => 'buildComputerPdf',
               'pri' => 'buildPrinterPdf',
               'pho' => 'buildPhonePdf',
            };
            // Activar watermark estático ANTES de que se construya el PDF
            // para que Header() lo dibuje en cada página al agregarla
            $wm_text = trim($config['watermark_text'] ?? '');
            PluginResponsivasPDF::$global_watermark      = true;
            PluginResponsivasPDF::$global_watermark_text = $wm_text !== '' ? $wm_text : __('PREVIEW', 'responsivas');
            $result = self::$method($user_id);
            PluginResponsivasPDF::$global_watermark = false; // reset
            $result['pdf']->SetTitle(match ($type) {
               'pc'  => __('Preview - Computer Responsibility - ', 'responsivas') . $user->getFriendlyName(),
               'pri' => __('Preview - Printer Responsibility - ',  'responsivas') . $user->getFriendlyName(),
               'pho' => __('Preview - Phone Loan - ',              'responsivas') . $user->getFriendlyName(),
            });
            return $result;
         } catch (RuntimeException) {
            // Caída silenciosa solo en errores esperados (activos faltantes, plantilla vacía) → usar demo
         }
      }

      // ── Sin activos o error → construir PDF demo ─────────────────────
      return self::buildDemoPdf($type, $user, $config);
   }

   /** Verifica si el usuario tiene teléfonos del tipo configurado */
   private static function userHasPhones(int $user_id, array $config): bool
   {
      global $DB;
      $type_id = (int)($config['cellphone_type_id'] ?? 0);
      if ($type_id === 0) return false;
      $iter = $DB->request([
         'COUNT' => 'cnt',
         'FROM'  => 'glpi_phones',
         'WHERE' => ['users_id' => $user_id, 'phonetypes_id' => $type_id, 'is_deleted' => 0],
      ]);
      return ($iter->current()['cnt'] ?? 0) > 0;
   }

   /* =====================================================
    * PDF completamente demo (sin datos reales de GLPI)
    * ===================================================== */
   private static function buildDemoPdf(string $type, User $user, array $config): array
   {
      global $CFG_GLPI;

      $full_name         = $user->getFriendlyName();
      $full_name_safe    = e($full_name);
      $company_name      = e($config['company_name'] ?? 'Mi Empresa');
      $real_employee     = trim($user->fields['registration_number'] ?? '');
      $demo_emp          = $real_employee !== '' ? e($real_employee) : 'EMP-001';
      $show_employee     = (int)($config['show_employee_number'] ?? 0);
      $th_bg             = '#E6E6E6';
      $td_bg             = '#FFFFFF';

      // Ubicación desde la entidad activa (igual que los builds reales)
      $entity = new Entity();
      $entity->getFromDB(Session::getActiveEntity());
      $location = e(implode(', ', array_filter([
         $entity->fields['town']    ?? '',
         $entity->fields['state']   ?? '',
         $entity->fields['country'] ?? '',
      ]))) ?: 'Hermosillo, Sonora, México';

      // Testigos / representante reales si están configurados, si no → demo
      $t1_id  = (int)($config['testigo_1']     ?? 0);
      $t2_id  = (int)($config['testigo_2']     ?? 0);
      $rep_id = (int)($config['representante'] ?? 0);
      $testigo1 = ($t1_id  > 0 && ($n = nombreUsuario($t1_id))  !== '') ? $n : __('Demo Witness 1',     'responsivas');
      $testigo2 = ($t2_id  > 0 && ($n = nombreUsuario($t2_id))  !== '') ? $n : __('Demo Witness 2',     'responsivas');
      $rep      = ($rep_id > 0 && ($n = nombreUsuario($rep_id)) !== '') ? $n : __('Demo Representative', 'responsivas');

      // Entity address/postcode (igual que el build real de teléfono)
      $address  = e($entity->fields['address']  ?? '');
      $postcode = e($entity->fields['postcode']  ?? '');

      // Estado al azar de los existentes en GLPI
      global $DB;
      $states     = iterator_to_array($DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_states', 'LIMIT' => 10]));
      $demo_state = !empty($states) ? e(reset($states)['name']) : 'En uso';

      $fecha_header = fechaATexto($_SESSION['glpi_currenttime'], $config['timezone']);

      // ── Computadora ─────────────────────────────────────────────────
      if ($type === 'pc') {
         $pdf = self::makePdf('pc',
            __('Preview - Computer Responsibility - ', 'responsivas') . $full_name,
            'Vista previa responsiva de computadora',
            'vista previa, responsiva, computadora, activos, TI',
            $location, $fecha_header, $config, 30.0, true
         );
         $pdf->AddPage();
         $pdf->setQrForPage($pdf->getPage(), $CFG_GLPI['url_base']);

         $employee_line      = ($show_employee && $demo_emp) ? (__("Employee No.: ", "responsivas") . $demo_emp) : '';
         $employee_line_html = $employee_line ? "<br>{$employee_line}" : '';

         $pc_vars = [
            '{nombre}' => e($full_name), '{empresa}' => $company_name,
            '{num_empleado}' => $demo_emp,   '{activo}' => 'PC-DEMO-001',
            '{serie}' => 'SN-DEMO-123456',   '{marca}'  => 'Dell',
            '{modelo}' => 'Latitude 5540',   '{tipo}'   => 'Laptop',
            '{estado}' => $demo_state,
            '{fecha}' => e(fechaATexto($_SESSION['glpi_currenttime'], $config['timezone'])),
            '{lugar}' => $location,
         ];
         $pc_titulo = responsivasApplyTemplate($config['pc_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO', $pc_vars);
         $pc_intro  = responsivasApplyTemplate($config['pc_intro']  ?? '', $pc_vars);
         $pc_cuerpo = responsivasRenderTemplate(responsivasApplyTemplate($config['pc_cuerpo'] ?? '', $pc_vars));

         $show_demo_pc_sigs = (int)($config['pc_show_comodato_sigs'] ?? 0) === 1;
         $demo_pc_rep       = $show_demo_pc_sigs ? $rep : '';
         $pdf->writeHTML(self::renderPcPage(
            $pc_titulo, $pc_intro, $pc_cuerpo,
            'Dell', 'Latitude 5540', 'SN-DEMO-123456',
            'Intel Core i5-1345U', '1.60 GHz',
            '16 GB DDR4', 'Windows 11 Pro', 'SSD 512 GB',
            'Laptop', $demo_state,
            e(__('Demo equipment for template preview', 'responsivas')),
            '', $full_name_safe, $employee_line_html, $th_bg, $td_bg,
            $show_demo_pc_sigs, $demo_pc_rep
         ), true, false, true, false, '');
         return ['pdf' => $pdf, 'filename' => self::makeFilename('Responsiva_Computo_DEMO', $full_name)];
      }

      // ── Impresora ────────────────────────────────────────────────────
      if ($type === 'pri') {
         $pdf = self::makePdf('pri',
            __('Preview - Printer Responsibility - ', 'responsivas') . $full_name,
            'Vista previa responsiva de impresora',
            'vista previa, responsiva, impresora, activos, TI',
            $location, $fecha_header, $config, 40.0, true
         );
         $pdf->AddPage();
         $pdf->setQrForPage($pdf->getPage(), $CFG_GLPI['url_base']);

         $employee_line = ($show_employee && $demo_emp) ? (__("Employee No.: ", "responsivas") . $demo_emp) : '';
         $full_safe     = $full_name_safe;

         $pri_vars = [
            '{nombre}' => e($full_name), '{empresa}' => $company_name,
            '{num_empleado}' => $demo_emp,   '{activo}' => 'IMP-DEMO-001',
            '{serie}' => 'SN-IMP-789012',    '{marca}'  => 'HP',
            '{modelo}' => 'LaserJet Pro M404n', '{tipo}' => 'Impresora',
            '{estado}' => $demo_state,
            '{fecha}' => e(fechaATexto($_SESSION['glpi_currenttime'], $config['timezone'])),
            '{lugar}' => $location,
         ];
         $pri_titulo = responsivasApplyTemplate($config['pri_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO', $pri_vars);
         $pri_intro  = responsivasApplyTemplate($config['pri_intro']  ?? '', $pri_vars);
         $pri_cuerpo = responsivasRenderTemplate(responsivasApplyTemplate($config['pri_cuerpo'] ?? '', $pri_vars));

         $show_demo_pri_sigs = (int)($config['pri_show_comodato_sigs'] ?? 0) === 1;
         $demo_pri_rep       = $show_demo_pri_sigs ? $rep : '';
         $pdf->writeHTML(self::renderPriPage(
            $pri_titulo, $pri_intro, $pri_cuerpo,
            'HP', 'LaserJet Pro M404n', 'SN-IMP-789012',
            'Impresora', $demo_state,
            e(__('Demo printer for template preview', 'responsivas')),
            $full_safe, $employee_line, $th_bg, $td_bg,
            $show_demo_pri_sigs, $demo_pri_rep
         ), true, false, true, false, '');
         return ['pdf' => $pdf, 'filename' => self::makeFilename('Responsiva_Impresora_DEMO', $full_name)];
      }

      // ── Teléfono ─────────────────────────────────────────────────────
      $pdf = self::makePdf('pho',
         __('Preview - Phone Loan - ', 'responsivas') . $full_name,
         'Vista previa comodato de teléfono',
         'vista previa, comodato, teléfono, activos, TI',
         $location, $fecha_header, $config, 25.0, true
      );
      $pdf->AddPage();
      $pdf->setQrForPage($pdf->getPage(), $CFG_GLPI['url_base']);

      $currency      = !empty($config['currency']) ? $config['currency'] : '$';
      $employee_line = ($show_employee && $demo_emp) ? (__("Employee No.: ", "responsivas") . $demo_emp) : '';

      $pho_vars = [
         '{nombre}'             => e($full_name),    '{empresa}'       => $company_name,
         '{num_empleado}'       => $demo_emp,         '{activo}'        => 'CEL-DEMO-001',
         '{serie_uuid}'         => 'UUID-DEMO-001',   '{imei}'          => '352999DEMO0001',
         '{marca}'              => 'Samsung',          '{modelo}'        => 'Galaxy A54 5G',
         '{estado}'             => $demo_state,
         '{almacenamiento}'     => '128 GB',           '{ram}'           => '6 GB',
         '{linea}'              => '662-100-0001',     '{precio}'        => '$ 7,500.00',
         '{fecha}'              => e(fechaATexto($_SESSION['glpi_currenttime'], $config['timezone'])),
         '{lugar}'              => $location,
         '{direccion}'          => $address,
         '{cp}'                 => $postcode,
         '{hora}'               => (new DateTime('now', new DateTimeZone($config['timezone'])))->format('H') . ':00',
         '{testigo1}'           => e($testigo1),       '{testigo2}'      => e($testigo2),
         '{representante}'      => e($rep),
         '{clausula_vida_util}' => 'Se establece como <strong>vida útil</strong> un periodo de 24 meses desde la fecha de asignación.',
      ];
      $pho_titulo    = responsivasApplyTemplate($config['pho_titulo'] ?? 'CONTRATO DE COMODATO', $pho_vars);
      $pho_apertura  = responsivasApplyTemplate($config['pho_apertura']  ?? '', $pho_vars);
      $pho_clausulas = responsivasRenderTemplate(responsivasApplyTemplate($config['pho_clausulas'] ?? '', $pho_vars));
      $pho_testigos  = responsivasApplyTemplate($config['pho_testigos']  ?? '', $pho_vars);

      $pdf->writeHTML(self::renderPhoPage(
         $pho_titulo, $pho_apertura, $pho_clausulas, $pho_testigos,
         e($rep), $full_name_safe, $employee_line,
         e($testigo1), e($testigo2)
      ), true, false, true, false, '');
      return ['pdf' => $pdf, 'filename' => self::makeFilename('Comodato_Telefono_DEMO', $full_name)];
   }


}