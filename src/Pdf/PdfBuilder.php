<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Pdf;

use GlpiPlugin\Responsivas\Exception\MissingEntityLocationException;
use GlpiPlugin\Responsivas\Utils;
use GlpiPlugin\Responsivas\Paths;

/**
 * PdfBuilder
 *
 * Fuente única de verdad para la construcción de PDFs de responsivas.
 * Tanto los archivos front (descarga directa) como el generador de correo
 * usan este builder — si modificas el contenido de un PDF aquí, el cambio
 * aplica automáticamente en ambos contextos.
 *
 * Cada método build* devuelve ['pdf' => PDF, 'filename' => string]
 * o lanza RuntimeException con un mensaje descriptivo en caso de error.
 */
class PdfBuilder
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
    * HELPER: autorización de objetos y entidades
    * Centraliza la comprobación para descarga, preview y correo.
    * ===================================================== */
   private static function loadViewableItem(\CommonDBTM $item, int $id): ?\CommonDBTM
   {
      if ($id <= 0 || !$item->getFromDB($id) || !$item->canView()) {
         return null;
      }

      if (array_key_exists('entities_id', $item->fields)
          && !\Session::haveAccessToEntity((int) $item->fields['entities_id'])) {
         return null;
      }

      return $item;
   }

   private static function filterViewableRows(string $itemClass, array $rows): array
   {
      $allowed = [];
      foreach ($rows as $row) {
         $id = (int) ($row['id'] ?? 0);
         /** @var \CommonDBTM $item */
         $item = new $itemClass();
         if (self::loadViewableItem($item, $id) !== null) {
            $allowed[] = $item->fields;
         }
      }
      return $allowed;
   }

   /* =====================================================
    * HELPER: user creator
    * ===================================================== */
   private static function getCreator(): string
   {
      if ($uid = \Session::getLoginUserID()) {
         $u = new \User();
         if ($u->getFromDB($uid)) {
            return $u->getFriendlyName();
         }
      }
      return 'GLPI';
   }

   /**
    * Obtiene la ubicación de la entidad activa exactamente con el mismo origen
    * para documentos reales y previews: ciudad, estado y país configurados
    * en la entidad de GLPI.
    */
   public static function validateActiveEntityLocation(): void
   {
      self::getActiveEntityLocation();
   }

   private static function getActiveEntityLocation(): string
   {
      $data = self::getActiveEntityLocationData();

      if ($data['missing'] !== []) {
         throw new MissingEntityLocationException(
            sprintf(
               __('The active entity is missing required location data: %s. Complete the City and State fields in the entity before generating the responsibility document.', 'responsivas'),
               implode(', ', $data['missing'])
            )
         );
      }

      return Utils::escape(implode(', ', $data['parts']));
   }

   /**
    * Returns the active entity location and the required fields that are missing.
    * City and State are mandatory for responsibility documents. Country is
    * included when configured but is intentionally optional for compatibility
    * with existing GLPI installations.
    *
    * @return array{parts: string[], missing: string[]}
    */
   private static function getActiveEntityLocationData(): array
   {
      $entity = new \Entity();
      if (!$entity->getFromDB(\Session::getActiveEntity())) {
         throw new \RuntimeException(__('Could not retrieve the active entity.', 'responsivas'));
      }

      $town = trim((string)($entity->fields['town'] ?? ''));
      $state = trim((string)($entity->fields['state'] ?? ''));
      $country = trim((string)($entity->fields['country'] ?? ''));

      $missing = [];
      if ($town === '') {
         $missing[] = __('City', 'responsivas');
      }
      if ($state === '') {
         $missing[] = __('State', 'responsivas');
      }

      $parts = array_values(array_filter([$town, $state, $country], static fn(string $value): bool => $value !== ''));

      return [
         'parts' => $parts,
         'missing' => $missing,
      ];
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
  <td colspan="6"><strong>{$l['associated_devices']}</strong></td>
</tr>
<tr style="background-color:{$th_bg};">
  <td width="16.6667%"><strong>{$l['device']}</strong></td>
  <td width="16.6667%"><strong>{$l['brand']}</strong></td>
  <td width="16.6667%"><strong>{$l['model']}</strong></td>
  <td width="16.6667%"><strong>{$l['serial']}</strong></td>
  <td width="16.6667%"><strong>{$l['asset']}</strong></td>
  <td width="16.6667%"><strong>{$l['condition']}</strong></td>
</tr>
HTML;
      $printed = true;
   }

   private static function appendDeviceRow(
      string &$html,
      string $device,
      string $brand,
      string $model,
      string $serial,
      string $asset,
      string $condition,
      string $td_bg
   ): void {
      $html .= '<tr style="background-color:' . $td_bg . ';">'
         . '<td width="16.6667%">' . Utils::escape($device) . '</td>'
         . '<td width="16.6667%">' . Utils::escape($brand) . '</td>'
         . '<td width="16.6667%">' . Utils::escape($model) . '</td>'
         . '<td width="16.6667%" style="white-space:nowrap;font-size:8pt;">' . Utils::escape($serial) . '</td>'
         . '<td width="16.6667%" style="white-space:nowrap;font-size:8pt;">' . Utils::escape($asset) . '</td>'
         . '<td width="16.6667%">' . Utils::escape($condition) . '</td>'
         . '</tr>';
   }

   /**
    * Renders the computer asset table used by standard responsibilities and
    * manual inspection/return forms. The optional cellpadding lets the manual
    * one-page layout compact the same table without maintaining a second copy.
    */
   private static function renderComputerAssetTable(
      string $brand,
      string $model,
      string $serial,
      string $cpu,
      string $speed,
      string $asset,
      string $identification,
      string $ram,
      string $os,
      string $storage,
      string $type,
      string $state,
      string $comments,
      string $dispositivosHtml,
      string $thBg,
      string $tdBg,
      float $cellPadding = 3.0
   ): string {
      $l = self::lbl();
      $padding = rtrim(rtrim(number_format($cellPadding, 2, '.', ''), '0'), '.');

      return '<table border="1" cellpadding="' . $padding . '" cellspacing="0" width="100%">'
         . '<tr style="background-color:' . $thBg . ';">'
         . '<td width="16.6667%"><strong>' . $l['brand'] . '</strong></td>'
         . '<td width="16.6667%"><strong>' . $l['model'] . '</strong></td>'
         . '<td width="16.6667%"><strong>' . $l['serial'] . '</strong></td>'
         . '<td width="16.6667%"><strong>' . $l['processor'] . '</strong></td>'
         . '<td width="16.6667%"><strong>' . $l['speed'] . '</strong></td>'
         . '<td width="16.6667%"><strong>' . $l['asset'] . '</strong></td></tr>'
         . '<tr style="background-color:' . $tdBg . ';">'
         . '<td>' . $brand . '</td><td>' . $model . '</td><td>' . $serial . '</td><td>' . $cpu . '</td><td>' . $speed . '</td><td>' . $asset . '</td></tr>'
         . '<tr style="background-color:' . $thBg . ';">'
         . '<td><strong>' . $l['ram'] . '</strong></td><td><strong>' . $l['os'] . '</strong></td><td><strong>' . $l['storage'] . '</strong></td><td><strong>' . $l['type'] . '</strong></td><td><strong>' . $l['condition'] . '</strong></td><td><strong>' . $l['identification'] . '</strong></td></tr>'
         . '<tr style="background-color:' . $tdBg . ';">'
         . '<td>' . $ram . '</td><td>' . $os . '</td><td>' . $storage . '</td><td>' . $type . '</td><td>' . $state . '</td><td>' . $identification . '</td></tr>'
         . '<tr style="background-color:' . $thBg . ';"><td colspan="6"><strong>' . $l['comments'] . '</strong></td></tr>'
         . '<tr style="background-color:' . $tdBg . ';"><td colspan="6">' . $comments . '</td></tr>'
         . $dispositivosHtml
         . '</table>';
   }

   /* =====================================================
    * PDF FOR COMPUTERS
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
         throw new \RuntimeException(sprintf(
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
         'associated_devices' => __('Associated devices', 'responsivas'),
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
         'asset'         => __('Asset', 'responsivas'),
         'identification' => __('Identification', 'responsivas'),
         'inspection_date' => __('Inspection date', 'responsivas'),
         'visual_inspection' => __('Visual inspection / physical condition', 'responsivas'),
         'delivery_condition' => __('Delivery condition', 'responsivas'),
         'return_condition' => __('Return condition', 'responsivas'),
         'observations' => __('Observations', 'responsivas'),
         'no_observations' => __('No visual observations recorded.', 'responsivas'),
         'marker_details' => __('Marker details', 'responsivas'),
         'major' => __('Major', 'responsivas'),
         'minor' => __('Minor', 'responsivas'),
         'inspection_note' => __('Inspection record kept with the responsibility document.', 'responsivas'),
      ];
   }

   /** Fixed, print-ready form intended to be completed by hand. */
   
   private static function getManualAssets(string $itemtype, int $userId): array
   {
      $classes = [
         'Computer' => \Computer::class,
         'Printer'  => \Printer::class,
         'Phone'    => \Phone::class,
      ];

      if ($userId <= 0 || !isset($classes[$itemtype])) {
         return [];
      }

      $criteria = [
         'users_id'   => $userId,
         'is_deleted' => 0,
      ];

      if ($itemtype === 'Phone') {
         $cfg = \Config::getConfigurationValues('plugin_responsivas');
         $phoneTypeId = (int)($cfg['cellphone_type_id'] ?? 0);
         if ($phoneTypeId > 0) {
            $criteria['phonetypes_id'] = $phoneTypeId;
         }
      }

      $class = $classes[$itemtype];
      $assets = [];

      foreach ((new $class())->find($criteria) as $row) {
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }

         $item = new $class();
         if (self::loadViewableItem($item, $id) !== null) {
            $assets[] = $item->fields;
         }
      }

      return $assets;
   }

   private static function getManualComputerAssociatedDevices(int $computerId, int $userId): array
   {
      global $DB;

      if ($computerId <= 0 || $userId <= 0) {
         return [];
      }

      $devices = [];
      $append = static function (array &$target, array $row, string $deviceType, string $modelName = ''): void {
         $target[] = [
            'device'    => $deviceType,
            'brand'     => Utils::dropdownName((int)($row['manufacturers_id'] ?? 0), 'glpi_manufacturers', __('Not specified', 'responsivas')),
            'model'     => trim($modelName) !== '' ? trim($modelName) : __('Not specified', 'responsivas'),
            'serial'    => trim((string)($row['serial'] ?? '')) !== '' ? trim((string)$row['serial']) : __('N/A', 'responsivas'),
            'asset'     => trim((string)($row['otherserial'] ?? '')) !== '' ? trim((string)$row['otherserial']) : __('N/A', 'responsivas'),
            'condition' => Utils::dropdownName((int)($row['states_id'] ?? 0), 'glpi_states', __('Not specified', 'responsivas')),
         ];
      };

      foreach ($DB->request([
         'SELECT'     => [
            'glpi_monitors.id', 'glpi_monitors.serial', 'glpi_monitors.otherserial',
            'glpi_monitors.states_id', 'glpi_monitors.manufacturers_id', 'glpi_monitors.monitormodels_id',
         ],
         'FROM'       => 'glpi_assets_assets_peripheralassets',
         'INNER JOIN' => [
            'glpi_monitors' => [
               'ON' => [
                  'glpi_assets_assets_peripheralassets' => 'items_id_peripheral',
                  'glpi_monitors' => 'id',
               ],
            ],
         ],
         'WHERE'      => [
            'glpi_assets_assets_peripheralassets.itemtype_asset'     => 'Computer',
            'glpi_assets_assets_peripheralassets.items_id_asset'     => $computerId,
            'glpi_assets_assets_peripheralassets.itemtype_peripheral' => 'Monitor',
            'glpi_assets_assets_peripheralassets.is_deleted'         => 0,
            'glpi_monitors.users_id'                                  => $userId,
         ],
      ]) as $row) {
         $monitor = new \Monitor();
         if (self::loadViewableItem($monitor, (int)($row['id'] ?? 0)) === null) {
            continue;
         }
         $model = Utils::dropdownName((int)($row['monitormodels_id'] ?? 0), 'glpi_monitormodels', '');
         $append($devices, $row, __('Monitor', 'responsivas'), $model);
      }

      foreach ($DB->request([
         'SELECT'     => [
            'glpi_peripherals.id', 'glpi_peripherals.name', 'glpi_peripherals.serial',
            'glpi_peripherals.otherserial', 'glpi_peripherals.states_id', 'glpi_peripherals.manufacturers_id',
            'glpi_peripheraltypes.name AS tipo', 'glpi_peripheralmodels.name AS modelo',
         ],
         'FROM'       => 'glpi_assets_assets_peripheralassets',
         'INNER JOIN' => [
            'glpi_peripherals' => [
               'ON' => [
                  'glpi_assets_assets_peripheralassets' => 'items_id_peripheral',
                  'glpi_peripherals' => 'id',
               ],
            ],
            'glpi_peripheraltypes' => [
               'ON' => [
                  'glpi_peripherals' => 'peripheraltypes_id',
                  'glpi_peripheraltypes' => 'id',
               ],
            ],
         ],
         'LEFT JOIN' => [
            'glpi_peripheralmodels' => [
               'ON' => [
                  'glpi_peripherals' => 'peripheralmodels_id',
                  'glpi_peripheralmodels' => 'id',
               ],
            ],
         ],
         'WHERE'      => [
            'glpi_assets_assets_peripheralassets.itemtype_asset'     => 'Computer',
            'glpi_assets_assets_peripheralassets.items_id_asset'     => $computerId,
            'glpi_assets_assets_peripheralassets.itemtype_peripheral' => 'Peripheral',
            'glpi_assets_assets_peripheralassets.is_deleted'         => 0,
            'glpi_peripherals.users_id'                               => $userId,
         ],
      ]) as $row) {
         $peripheral = new \Peripheral();
         if (self::loadViewableItem($peripheral, (int)($row['id'] ?? 0)) === null) {
            continue;
         }
         $append(
            $devices,
            $row,
            trim((string)($row['tipo'] ?? '')) !== '' ? (string)$row['tipo'] : __('Device', 'responsivas'),
            (string)($row['modelo'] ?? '')
         );
      }

      return $devices;
   }

   private static function getManualPreviewDisplayData(string $itemtype, ?\User $user): array
   {
      $userName = $user ? $user->getFriendlyName() : __('Preview user', 'responsivas');

      $common = [
         'asset'   => 'DEMO-001',
         'name'    => 'Equipo de demostración',
         'user'    => $userName,
         'brand'   => $itemtype === 'Phone' ? 'Samsung' : ($itemtype === 'Printer' ? 'HP' : 'Dell'),
         'model'   => $itemtype === 'Phone' ? 'Galaxy A54 5G' : ($itemtype === 'Printer' ? 'LaserJet Pro M404n' : 'Inspiron 3520'),
         'serial'  => $itemtype === 'Phone' ? 'UUID-DEMO-001' : 'SERIE-DEMO-001',
         'type'    => $itemtype === 'Phone' ? 'Celular' : ($itemtype === 'Printer' ? 'Impresora' : 'Computadora'),
         'state'   => __('Used', 'responsivas'),
         'comment' => __('Demo data for template preview', 'responsivas'),
         'os'      => $itemtype === 'Computer' ? 'Windows 11 Pro' : __('Not specified', 'responsivas'),
         'ram'     => $itemtype === 'Computer' || $itemtype === 'Phone' ? '8 GB' : __('Not specified', 'responsivas'),
         'storage' => $itemtype === 'Computer' || $itemtype === 'Phone' ? '256 GB SSD' : __('Not specified', 'responsivas'),
         'cpu'     => $itemtype === 'Computer' ? 'Intel Core i5' : __('Not specified', 'responsivas'),
         'speed'   => $itemtype === 'Computer' ? '2.40 GHz' : __('Not specified', 'responsivas'),
         'imei'    => $itemtype === 'Phone' ? '352999DEMO0001' : __('Not specified', 'responsivas'),
         'uuid'    => $itemtype === 'Phone' ? 'UUID-DEMO-001' : __('Not specified', 'responsivas'),
         'line'    => $itemtype === 'Phone' ? '662-100-0001' : __('Not specified', 'responsivas'),
         'price'   => $itemtype === 'Phone' ? '$ 7,500.00' : __('Not specified', 'responsivas'),
         'associated_devices' => $itemtype === 'Computer' ? [
            [
               'device' => __('Monitor', 'responsivas'), 'brand' => 'Dell', 'model' => 'P2422H',
               'serial' => 'MON-DEMO-001', 'asset' => 'ACT-MON-001', 'condition' => __('Used', 'responsivas'),
            ],
            [
               'device' => __('Keyboard', 'responsivas'), 'brand' => 'Logitech', 'model' => 'K120',
               'serial' => 'KB-DEMO-001', 'asset' => 'ACT-KB-001', 'condition' => __('Used', 'responsivas'),
            ],
         ] : [],
      ];

      return $common;
   }

   private static function getManualAssetDisplayData(
      string $itemtype,
      array $asset,
      \User $user
   ): array {
      $modelMap = [
         'Computer' => ['computermodels_id', 'glpi_computermodels', 'computertypes_id', 'glpi_computertypes'],
         'Printer'  => ['printermodels_id', 'glpi_printermodels', 'printertypes_id', 'glpi_printertypes'],
         'Phone'    => ['phonemodels_id', 'glpi_phonemodels', 'phonetypes_id', 'glpi_phonetypes'],
      ];
      [$modelField, $modelTable, $typeField, $typeTable] = $modelMap[$itemtype];
      $notSpecified = __('Not specified', 'responsivas');

      $display = [
         'asset'   => trim((string)($asset['otherserial'] ?? '')),
         'name'    => trim((string)($asset['name'] ?? '')),
         'user'    => $user->getFriendlyName(),
         'brand'   => trim((string)Utils::dropdownName((int)($asset['manufacturers_id'] ?? 0), 'glpi_manufacturers', $notSpecified)),
         'model'   => trim((string)Utils::dropdownName((int)($asset[$modelField] ?? 0), $modelTable, $notSpecified)),
         'serial'  => $itemtype === 'Phone'
            ? trim((string)($asset['uuid'] ?? ''))
            : trim((string)($asset['serial'] ?? '')),
         'type'    => trim((string)Utils::dropdownName((int)($asset[$typeField] ?? 0), $typeTable, $notSpecified)),
         'state'   => trim((string)Utils::dropdownName((int)($asset['states_id'] ?? 0), 'glpi_states', $notSpecified)),
         'comment' => trim((string)($asset['comment'] ?? $notSpecified)),
         'os'      => $notSpecified,
         'ram'     => $notSpecified,
         'storage' => $notSpecified,
         'cpu'     => $notSpecified,
         'speed'   => $notSpecified,
         'imei'    => trim((string)($asset['serial'] ?? '')),
         'uuid'    => trim((string)($asset['uuid'] ?? '')),
         'line'    => $notSpecified,
         'price'   => $notSpecified,
         'associated_devices' => [],
      ];

      if ($display['asset'] === '') {
         $display['asset'] = $display['name'] !== '' ? $display['name'] : $notSpecified;
      }
      if ($display['serial'] === '') {
         $display['serial'] = $notSpecified;
      }
      if ($display['comment'] === '') {
         $display['comment'] = $notSpecified;
      }

      if ($itemtype === 'Computer') {
         foreach ((new \Item_OperatingSystem())->find([
            'items_id' => (int)($asset['id'] ?? 0), 'itemtype' => 'Computer', 'is_deleted' => 0,
         ], ['date_mod DESC'], 1) as $row) {
            $parts = [];
            foreach ([
               ['operatingsystems_id', 'glpi_operatingsystems'],
               ['operatingsystemversions_id', 'glpi_operatingsystemversions'],
               ['operatingsystemeditions_id', 'glpi_operatingsystemeditions'],
            ] as [$field, $table]) {
               $v = Utils::dropdownName((int)($row[$field] ?? 0), $table, '');
               if ($v !== '') $parts[] = $v;
            }
            if ($parts !== []) {
               $display['os'] = implode(' ', $parts);
               break;
            }
         }

         $ram = [];
         foreach ((new \Item_DeviceMemory())->find([
            'items_id' => (int)($asset['id'] ?? 0), 'itemtype' => 'Computer', 'is_deleted' => 0,
         ]) as $row) {
            $mem = new \DeviceMemory();
            if ($mem->getFromDB((int)($row['devicememories_id'] ?? 0)) && !empty($mem->fields['designation'])) {
               $ram[] = $mem->fields['designation'];
            }
         }
         if ($ram !== []) $display['ram'] = implode(' + ', $ram);

         $disks = [];
         foreach ((new \Item_DeviceHardDrive())->find([
            'items_id' => (int)($asset['id'] ?? 0), 'itemtype' => 'Computer', 'is_deleted' => 0,
         ]) as $row) {
            $disk = new \DeviceHardDrive();
            if ($disk->getFromDB((int)($row['deviceharddrives_id'] ?? 0)) && !empty($disk->fields['designation'])) {
               $disks[] = $disk->fields['designation'];
            }
         }
         if ($disks !== []) $display['storage'] = implode(', ', array_unique($disks));

         foreach ((new \Item_DeviceProcessor())->find([
            'items_id' => (int)($asset['id'] ?? 0), 'itemtype' => 'Computer', 'is_deleted' => 0,
         ], ['id DESC'], 1) as $row) {
            $cpu = new \DeviceProcessor();
            if ($cpu->getFromDB((int)($row['deviceprocessors_id'] ?? 0))) {
               $mfr = Utils::dropdownName((int)($cpu->fields['manufacturers_id'] ?? 0), 'glpi_manufacturers', '');
               $designation = trim((string)($cpu->fields['designation'] ?? ''));
               $display['cpu'] = trim($mfr . ' ' . $designation) !== '' ? trim($mfr . ' ' . $designation) : $notSpecified;
               if (!empty($cpu->fields['frequence'])) {
                  $display['speed'] = number_format((float)$cpu->fields['frequence'] / 1000, 2) . ' GHz';
               }
               break;
            }
         }
      }

      if ($itemtype === 'Computer') {
         $display['associated_devices'] = self::getManualComputerAssociatedDevices(
            (int)($asset['id'] ?? 0),
            $user->getID()
         );
      }

      if ($itemtype === 'Phone') {
         $phoneRam = [];
         foreach ((new \Item_DeviceMemory())->find([
            'items_id' => (int)($asset['id'] ?? 0), 'itemtype' => 'Phone', 'is_deleted' => 0,
         ]) as $row) {
            $designation = Utils::dropdownName((int)($row['devicememories_id'] ?? 0), 'glpi_devicememories', '');
            if ($designation !== '') $phoneRam[] = $designation;
         }
         if ($phoneRam !== []) $display['ram'] = implode(' + ', $phoneRam);

         $phoneStorage = [];
         foreach ((new \Item_DeviceHardDrive())->find([
            'items_id' => (int)($asset['id'] ?? 0), 'itemtype' => 'Phone', 'is_deleted' => 0,
         ]) as $row) {
            $designation = Utils::dropdownName((int)($row['deviceharddrives_id'] ?? 0), 'glpi_deviceharddrives', '');
            if ($designation !== '') $phoneStorage[] = $designation;
         }
         if ($phoneStorage !== []) $display['storage'] = implode(', ', array_unique($phoneStorage));

         $lineRows = (new \Item_Line())->find([
            'items_id' => (int)($asset['id'] ?? 0),
            'itemtype' => 'Phone',
         ]);
         $lineRow = reset($lineRows);
         if ($lineRow && !empty($lineRow['lines_id'])) {
            $line = new \Line();
            if ($line->getFromDB((int)$lineRow['lines_id'])) {
               $callerNum = trim((string)($line->fields['caller_num'] ?? ''));
               $lineName  = trim((string)($line->fields['name'] ?? ''));
               $display['line'] = $callerNum !== '' ? $callerNum : ($lineName !== '' ? $lineName : $notSpecified);
            }
         }
         $display['uuid'] = trim((string)($asset['uuid'] ?? $notSpecified)) ?: $notSpecified;
      }

      return $display;
   }

   

   

public static function buildManualFormPdf(string $itemtype, string $movement, int $userId = 0, bool $preview = false): array
   {
      global $CFG_GLPI;
      if (!in_array($itemtype, ['Computer', 'Printer', 'Phone'], true)) {
         throw new \InvalidArgumentException(__('Invalid asset type.', 'responsivas'));
      }
      if (!in_array($movement, ['delivery', 'return'], true)) {
         throw new \InvalidArgumentException(__('Invalid manual form type.', 'responsivas'));
      }

      $config = \Config::getConfigurationValues('plugin_responsivas');
      $prefix = ['Computer' => 'pc', 'Printer' => 'pri', 'Phone' => 'pho'][$itemtype];
      $form = $movement === 'return' ? 'return' : 'inspection';

      $title = trim((string)($config[$prefix . '_' . $form . '_title'] ?? ''));
      $instructions = trim((string)($config[$prefix . '_' . $form . '_instructions'] ?? ''));
      if ($title === '') {
         $title = $movement === 'return'
            ? __('ASSET RETURN FORM', 'responsivas')
            : __('VISUAL ASSET CONDITION FORM', 'responsivas');
      }

      if ($instructions === '') {
         $instructions = $movement === 'return'
            ? __('Inspect the equipment before receiving it and record any relevant physical condition.', 'responsivas')
            : __('Inspect all visible surfaces before delivering the equipment and record any relevant physical condition.', 'responsivas');
      }
      $timezone = (string)($config['timezone'] ?? date_default_timezone_get());
      $dateText = Utils::dateToText(
         (string)($_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s')),
         $timezone
      );

      $location = '';
      try {
         $location = self::getActiveEntityLocation();
      } catch (\Throwable $e) {
         // Keep the manual document printable if the entity has no location.
      }

      $pdf = self::makePdf(
         $prefix,
         'Responsivas - ' . $title,
         $title,
         'responsivas, formulario, condicion, devolucion, activos',
         $location,
         $dateText,
         $config,
         24.0,
         false
      );
      // The exact manual format owns its own 4-position footer configuration.
      $pdf->setDocumentType($prefix . '_font_size', $prefix . '_' . $form);

      $assignedUser = null;
      if ($userId > 0) {
         $candidate = new \User();
         if ($candidate->getFromDB($userId) && $candidate->canView()) {
            $assignedUser = $candidate;
         }
      }

      $assets = $assignedUser ? self::getManualAssets($itemtype, $userId) : [];
      if ($assets === [] || $preview) {
         // Configuration preview is a template preview: always render one
         // complete manual sheet using representative values, never depend on
         // the administrator's assigned inventory.
         $assets = [[]];
      }

      $font = \Config::getConfigurationValue('core', 'pdffont');
      $fontSize = max(8, min(12, (int)($config[$prefix . '_font_size'] ?? 10)));
      $labelSize = max(7, $fontSize - 1);
      $escapeValue = static function (string $value): string {
         return Utils::escape($value !== '' ? $value : __('Not specified', 'responsivas'));
      };

      foreach ($assets as $asset) {
         $pdf->AddPage();

         // Manual forms use the same footer QR mechanism as standard
         // responsibility documents. For real documents, the QR points to
         // the exact GLPI asset record represented by the sheet.
         if ($preview) {
            // Keep manual previews visually consistent with standard previews:
            // show the QR in the footer, pointing to the GLPI base URL.
            $pdf->setQrForPage($pdf->getPage(), $CFG_GLPI['url_base']);
         } elseif (isset($asset['id'])) {
            $manualUrlMap = [
               'Computer' => '/front/computer.form.php?id=',
               'Printer'  => '/front/printer.form.php?id=',
               'Phone'    => '/front/phone.form.php?id=',
            ];
            if (isset($manualUrlMap[$itemtype])) {
               $pdf->setQrForPage(
                  $pdf->getPage(),
                  $CFG_GLPI['url_base'] . $manualUrlMap[$itemtype] . (int)$asset['id']
               );
            }
         }

         // Center the configured title as plain text. renderTemplate() returns
         // HTML intended for writeHTML(), so it must never be passed to Cell().
         $pdf->SetFont($font, 'B', $fontSize + 1);
         $titleText = trim(strip_tags(Utils::applyTemplate($title, [])));
         $pdf->SetX(15);
         $pdf->Cell(186, 7.5, $titleText, 0, 1, 'C');
         $pdf->Ln(0.5);

         $data = ($assignedUser && isset($asset['id']) && !$preview)
            ? self::getManualAssetDisplayData($itemtype, $asset, $assignedUser)
            : self::getManualPreviewDisplayData($itemtype, $assignedUser);

         $pdf->SetFont($font, '', $labelSize);
         if ($itemtype === 'Computer') {
            // Computer asset tag and Name are rendered in the shared six-column table.
            $identificationHtml = '';
         } else {
            // Printer and phone manuals keep the asset number on the left and place
            // the corresponding asset Name on the right. The recipient already
            // appears in the signature block, so there is no redundant Assigned to line.
            $identificationLabel = $itemtype === 'Phone'
               ? __('Phone identification', 'responsivas')
               : __('Printer identification', 'responsivas');
            $identificationHtml =
               '<table nobr="true" width="100%" cellpadding="0" cellspacing="0">'
               . '<tr>'
               . '<td width="50%" style="font-size:' . $labelSize . 'pt;">'
               . '<strong>' . Utils::escape(__('Asset identification', 'responsivas')) . ':</strong> '
               . $escapeValue((string)$data['asset'])
               . '</td>'
               . '<td width="50%" style="font-size:' . $labelSize . 'pt;">'
               . '<strong>' . Utils::escape($identificationLabel) . ':</strong> '
               . $escapeValue((string)($data['name'] ?? ''))
               . '</td>'
               . '</tr>'
               . '</table>';
         }
         if ($identificationHtml !== '') {
            $pdf->writeHTML(
               $identificationHtml,
               true,
               false,
               true,
               false,
               ''
            );
         }

         // The property table intentionally mirrors the normal responsibility PDF.
         if ($itemtype === 'Computer') {
            // Reuse the exact same computer asset table as the standard responsibility.
            // The smaller cell padding is only for the fixed one-page manual layout.
            $associatedDevices = (array)($data['associated_devices'] ?? []);
            $associatedCount = count($associatedDevices);
            $devicesHtml = '';
            $printedDevicesHeader = false;
            if ($associatedCount > 0) {
               self::appendDevicesHeader($devicesHtml, $printedDevicesHeader, '#E6E6E6');
               foreach ($associatedDevices as $device) {
                  self::appendDeviceRow(
                     $devicesHtml,
                     (string)($device['device'] ?? ''),
                     (string)($device['brand'] ?? ''),
                     (string)($device['model'] ?? ''),
                     (string)($device['serial'] ?? ''),
                     (string)($device['asset'] ?? ''),
                     (string)($device['condition'] ?? ''),
                     '#FFFFFF'
                  );
               }
            }

            $tablePadding = $associatedCount > 6 ? 1.5 : ($associatedCount > 3 ? 1.75 : 2.0);
            $assetTable = self::renderComputerAssetTable(
               $escapeValue((string)$data['brand']),
               $escapeValue((string)$data['model']),
               $escapeValue((string)$data['serial']),
               $escapeValue((string)$data['cpu']),
               $escapeValue((string)$data['speed']),
               $escapeValue((string)($data['asset'] ?? '')),
               $escapeValue((string)($data['name'] ?? '')),
               $escapeValue((string)$data['ram']),
               $escapeValue((string)$data['os']),
               $escapeValue((string)$data['storage']),
               $escapeValue((string)$data['type']),
               $escapeValue((string)$data['state']),
               $escapeValue((string)$data['comment']),
               $devicesHtml,
               '#E6E6E6',
               '#FFFFFF',
               $tablePadding
            );
         } elseif ($itemtype === 'Printer') {
            $table = '<table border="1" cellpadding="3" cellspacing="0" width="100%">'
               . '<tr style="background-color:#E6E6E6;">'
               . '<td width="20%"><strong>' . __('Brand', 'responsivas') . '</strong></td>'
               . '<td width="20%"><strong>' . __('Model', 'responsivas') . '</strong></td>'
               . '<td width="20%"><strong>' . __('Serial', 'responsivas') . '</strong></td>'
               . '<td width="20%"><strong>' . __('Type', 'responsivas') . '</strong></td>'
               . '<td width="20%"><strong>' . __('Condition', 'responsivas') . '</strong></td></tr>'
               . '<tr>'
               . '<td>' . $escapeValue($data['brand']) . '</td>'
               . '<td>' . $escapeValue($data['model']) . '</td>'
               . '<td>' . $escapeValue($data['serial']) . '</td>'
               . '<td>' . $escapeValue($data['type']) . '</td>'
               . '<td>' . $escapeValue($data['state']) . '</td></tr>'
               . '<tr style="background-color:#E6E6E6;"><td colspan="5"><strong>' . __('Comments', 'responsivas') . '</strong></td></tr>'
               . '<tr><td colspan="5">' . $escapeValue($data['comment']) . '</td></tr>'
               . '</table><br>';
         } else {
            $table = '<table border="1" cellpadding="3" cellspacing="0" width="100%">'
               . '<tr style="background-color:#E6E6E6;">'
               . '<td width="20%"><strong>' . __('Brand', 'responsivas') . '</strong></td>'
               . '<td width="20%"><strong>' . __('Model', 'responsivas') . '</strong></td>'
               . '<td width="20%"><strong>' . __('Serial', 'responsivas') . '</strong></td>'
               . '<td width="20%"><strong>' . __('Storage', 'responsivas') . '</strong></td>'
               . '<td width="20%"><strong>' . __('RAM', 'responsivas') . '</strong></td></tr>'
               . '<tr>'
               . '<td>' . $escapeValue($data['brand']) . '</td>'
               . '<td>' . $escapeValue($data['model']) . '</td>'
               . '<td>' . $escapeValue($data['serial']) . '</td>'
               . '<td>' . $escapeValue($data['storage']) . '</td>'
               . '<td>' . $escapeValue($data['ram']) . '</td></tr>'
               . '<tr style="background-color:#E6E6E6;">'
               . '<td><strong>' . __('Type', 'responsivas') . '</strong></td>'
               . '<td><strong>' . __('Condition', 'responsivas') . '</strong></td>'
               . '<td><strong>' . __('IMEI', 'responsivas') . '</strong></td>'
               . '<td><strong>' . __('Line', 'responsivas') . '</strong></td>'
               . '<td><strong>' . __('Asset', 'responsivas') . '</strong></td></tr>'
               . '<tr>'
               . '<td>' . $escapeValue($data['type']) . '</td>'
               . '<td>' . $escapeValue($data['state']) . '</td>'
               . '<td>' . $escapeValue($data['imei']) . '</td>'
               . '<td>' . $escapeValue($data['line']) . '</td>'
               . '<td>' . $escapeValue($data['asset']) . '</td></tr>'
               . '</table><br>';
         }

         $pdf->SetFont($font, '', $labelSize);
         $pdf->writeHTML($itemtype === 'Computer' ? $assetTable : $table, true, false, true, false, '');

         // Keep the condition heading and all four options on one compact row
         // in manual inspection/return forms to preserve space for the
         // visual map, notes and signatures on the same Letter page.
         $conditionLabel = $movement === 'return'
            ? __('Condition at return', 'responsivas')
            : __('Condition at delivery', 'responsivas');
         $conditions = [
            __('Excellent', 'responsivas'),
            __('Good', 'responsivas'),
            __('Fair', 'responsivas'),
            __('Damaged', 'responsivas'),
         ];
         // Remove the small empty band left by TCPDF after the property table
         // before the condition row. Keep the adjustment local to manual forms.
         $conditionY = max(25.0, $pdf->GetY() - 1.5);
         $conditionX = 15;
         $conditionHeadingW = 50;
         $conditionOptionW = 34;
         $pdf->SetFont($font, 'B', $fontSize);
         $pdf->SetXY($conditionX, $conditionY);
         $pdf->Cell($conditionHeadingW, 5.5, $conditionLabel, 0, 0, 'L');
         $conditionX += $conditionHeadingW;

         foreach ($conditions as $condition) {
            // Set the line width immediately before each rectangle so every
            // checkbox is rendered with the same print-safe thickness.
            $pdf->SetLineWidth(0.45);
            $pdf->Rect($conditionX, $conditionY + 0.5, 4.5, 4.5);
            $pdf->SetXY($conditionX + 6, $conditionY);
            $pdf->SetFont($font, '', $labelSize);
            $pdf->Cell($conditionOptionW - 6, 5.5, $condition, 0, 0, 'L');
            $conditionX += $conditionOptionW;
         }

         $pdf->SetLineWidth(0.2);
         $pdf->SetY($conditionY + 5);

         // General computer condition map: no laptop/desktop inference.
         $pdf->SetFont($font, 'B', $fontSize);
         $pdf->Cell(0, 5.5, __('Visual condition map', 'responsivas'), 0, 1, 'L');
         if ($itemtype === 'Computer') {
            $baseY = $pdf->GetY() + 1;
            // Keep the existing computer schematic exactly as designed. Associated
            // device rows adapt inside their own table; the schematic is never resized.
            // Slightly reduce the computer schematic so manuals with larger
            // associated-device tables keep enough vertical space for the
            // instructions, notes and signatures on the same Letter page.
            $mapWidth = ($preview && $itemtype === 'Computer') ? 148 : 150;
            $mapHeight = $mapWidth * (702 / 1863);
            $mapX = (210 - $mapWidth) / 2;
            $pdf->Image(Paths::assetPath('computer_general.png'), $mapX, $baseY, $mapWidth, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
            if ($preview) {
               $pdf->drawWatermarkAt(105, $baseY + ($mapHeight / 2));
            }
            $pdf->SetY($baseY + $mapHeight);
         } else {
            if ($itemtype === 'Phone') {
               $baseY = $pdf->GetY() + 1;
               $phoneMapW = 62;
               $phoneMapH = 62; // source is square; keep the full four-view diagram visible
               $phoneMapX = (210 - $phoneMapW) / 2;
               $pdf->Image(
                  Paths::assetPath('phone_general.png'),
                  $phoneMapX,
                  $baseY,
                  $phoneMapW,
                  $phoneMapH,
                  'PNG',
                  '',
                  '',
                  false,
                  300,
                  '',
                  false,
                  false,
                  0,
                  false,
                  false,
                  false
               );
               if ($preview) {
                  $pdf->drawWatermarkAt(105, $baseY + ($phoneMapH / 2));
               }
               // Reserve the full rendered height before continuing with Instructions.
               $pdf->SetY($baseY + $phoneMapH + 2);
            } elseif ($itemtype === 'Printer') {
               $baseY = $pdf->GetY() + 1;
               $pdf->Image(
                  Paths::assetPath('printer_general.png'),
                  70,
                  $baseY,
                  70,
                  0,
                  'PNG',
                  '',
                  '',
                  false,
                  300,
                  '',
                  false,
                  false,
                  0,
                  false,
                  false,
                  false
               );
               if ($preview) {
                  $pdf->drawWatermarkAt(105, $baseY + 28);
               }
               $pdf->SetY($baseY + 56);
            } else {
               $baseY = $pdf->GetY() + 1;
               $pdf->Image(
                  Paths::assetPath('printer_general.png'),
                  70,
                  $baseY,
                  70,
                  0,
                  'PNG',
                  '',
                  '',
                  false,
                  300,
                  '',
                  false,
                  false,
                  0,
                  false,
                  false,
                  false
               );
               $pdf->SetY($baseY + 56);
            }
         }

         // Editable Instructions followed by the compact icon-based damage guide.
         $pdf->SetY($pdf->GetY() + 0.75);
         $pdf->SetFont($font, 'B', max(8, $fontSize - 1));
         $pdf->Cell(0, 5, __('Instructions', 'responsivas'), 0, 1, 'L');

         $instructionText = trim(strip_tags(Utils::applyTemplate($instructions, [
            '{nombre}' => $data['user'] ?? '',
            '{activo}' => $data['asset'] ?? '',
            '{marca}'  => $data['brand'] ?? '',
            '{modelo}' => $data['model'] ?? '',
            '{serie}'  => $data['serial'] ?? '',
            '{estado}' => $data['state'] ?? '',
            '{fecha}'  => $dateText,
         ])));

         if ($instructionText !== '') {
            $pdf->SetFont($font, '', max(6.5, $fontSize - 2));
            $pdf->MultiCell(186, 4.2, $instructionText, 0, 'L', false, 1);
         }

         $guideItems = [
            ['icon' => 'scratch.png', 'title' => __('Scratches', 'responsivas'),
             'text' => __('Draw short lines over the scratched area.', 'responsivas')],
            ['icon' => 'impact.png', 'title' => __('Impacts / dents', 'responsivas'),
             'text' => __('Draw a circle around the damaged area.', 'responsivas')],
            ['icon' => 'wear.png', 'title' => __('Wear / use', 'responsivas'),
             'text' => __('Draw short waves over the worn area.', 'responsivas')],
            ['icon' => 'missing.png', 'title' => __('Missing parts / other', 'responsivas'),
             'text' => __('Draw an X over the missing part or component.', 'responsivas')],
         ];

         $guideY = $pdf->GetY() + 1.0;
         $iconSize = 5.5;
         $lineH = ($itemtype === 'Computer' && $associatedCount > 6) ? 4.4 : (($itemtype === 'Computer' && $associatedCount > 3) ? 4.7 : 5.0);

         foreach ($guideItems as $item) {
            $pdf->Image(
               Paths::assetPath('damage_icons/' . $item['icon']),
               15,
               $guideY + 0.1,
               $iconSize,
               $iconSize,
               'PNG',
               '',
               '',
               false,
               300,
               '',
               false,
               false,
               0,
               false,
               false,
               false
            );

            $pdf->SetXY(23, $guideY);
            $pdf->SetFont($font, 'B', max(6.5, $fontSize - 2));
            $pdf->Cell(48, $lineH, $item['title'] . ':', 0, 0, 'L');

            $pdf->SetFont($font, '', max(6.5, $fontSize - 2));
            $pdf->Cell(
               115,
               $lineH,
               $item['text'],
               0,
               1,
               'L'
            );

            $guideY += $lineH;
         }

         $pdf->SetY($guideY + 1);

         // Larger handwriting area because the compact guide releases vertical space.
         $pdf->SetFont($font, 'B', $fontSize);
         $pdf->Cell(0, 5.5, __('Additional notes', 'responsivas'), 0, 1, 'L');
         $notesY = $pdf->GetY();
         // Keep the handwriting box clearly visible after printing while making
         // additional computer device rows fit on the same Letter sheet.
         $compactLevel = ($itemtype === 'Computer')
            ? ($associatedCount > 10 ? 3 : ($associatedCount > 6 ? 2 : ($associatedCount > 3 ? 1 : 0)))
            : 0;
         $notesHeight = [20, 19, 18, 17][$compactLevel];
         // The previous layout reserved a large empty band below the notes box.
         // Keep only the space needed for the signature block so the manual
         // sheet remains on one Letter page even with a taller device table.
         // Add a few millimetres of breathing room before the signature block
         // so the handwritten signature has a slightly larger usable area.
         $notesAfter = [31, 29, 27, 25][$compactLevel];
         $pdf->SetLineWidth(0.45);
         $pdf->Rect(15, $notesY, 186, $notesHeight);
         $pdf->SetLineWidth(0.2);
         $pdf->SetY($notesY + $notesAfter);

         // Real signer names: technician assigned to asset, otherwise logged-in GLPI user.
         $technician = '';
         if (isset($asset['users_id_tech']) && (int)$asset['users_id_tech'] > 0) {
            $tech = new \User();
            if ($tech->getFromDB((int)$asset['users_id_tech']) && $tech->canView()) {
               $technician = $tech->getFriendlyName();
            }
         }

         if ($technician === '') {
            $loginId = (int)\Session::getLoginUserID();
            if ($loginId > 0) {
               $tech = new \User();
               if ($tech->getFromDB($loginId) && $tech->canView()) {
                  $technician = $tech->getFriendlyName();
               }
            }
         }

         if ($technician === '') {
            $technician = __('Not specified', 'responsivas');
         }

         $recipient = $data['user'] !== ''
            ? $data['user']
            : ($assignedUser ? $assignedUser->getFriendlyName() : __('Not specified', 'responsivas'));

         $pdf->SetFont($font, '', $labelSize);
         $pdf->Cell(93, 4.5, '________________________________________', 0, 0, 'C');
         $pdf->Cell(93, 4.5, '________________________________________', 0, 1, 'C');

         $pdf->SetFont($font, 'B', max(7, $labelSize));
         $pdf->Cell(93, 4.5, $technician, 0, 0, 'C');
         $pdf->Cell(93, 4.5, $recipient, 0, 1, 'C');

         $pdf->SetFont($font, '', max(6, $fontSize - 2));
         $pdf->Cell(93, 4, __('Technician', 'responsivas'), 0, 0, 'C');
         $pdf->Cell(93, 4, __('User', 'responsivas'), 0, 1, 'C');

      }
      $namePrefix = ['Computer' => 'Computadora', 'Printer' => 'Impresora', 'Phone' => 'Telefono'][$itemtype];
      $nameSuffix = $movement === 'return' ? 'Devolucion' : 'Inspeccion_Visual';
      return [
         'pdf' => $pdf,
         'filename' => self::makeFilename($namePrefix . '_' . $nameSuffix, $assignedUser ? $assignedUser->getFriendlyName() : 'Manual'),
      ];
   }








   public static function buildComputerPdf(int $user_id): array
   {
      global $DB, $CFG_GLPI;

      $config = \Config::getConfigurationValues('plugin_responsivas');
      self::validateTemplates('pc', $config);

      $user = new \User();
      if (!$user->getFromDB($user_id) || !$user->canView()) {
         throw new \RuntimeException(__('User not found.', 'responsivas'));
      }

      $computers = [];
      foreach ((new \Computer())->find(['users_id' => $user_id, 'is_deleted' => 0]) as $row) {
         $comp = new \Computer();
         if (self::loadViewableItem($comp, (int) ($row['id'] ?? 0)) !== null) {
            $computers[] = $comp;
         }
      }
      if (empty($computers)) {
         throw new \RuntimeException(__('The user has no assigned equipment.', 'responsivas'));
      }

      $entity = new \Entity();
      if (!$entity->getFromDB(\Session::getActiveEntity())) {
         throw new \RuntimeException(__('Could not retrieve the entity.', 'responsivas'));
      }

      $location = self::getActiveEntityLocation();

      $full_name       = $user->getFriendlyName();
      $employee_number = Utils::escape($user->fields['registration_number'] ?? '');
      $show_employee   = (int)($config['show_employee_number'] ?? 0);
      $company_name    = Utils::escape($config['company_name'] ?? '');
      $employee_line   = ($show_employee && $employee_number) ? (__("Employee No.: ", "responsivas") . $employee_number) : '';

      $th_bg = '#E6E6E6';
      $td_bg = '#FFFFFF';

      $show_both_sigs_pc  = (int)($config['pc_show_comodato_sigs'] ?? 0) === 1;
      $representante_pc   = '';
      if ($show_both_sigs_pc) {
         $rep_id = (int)($config['representante'] ?? 0);
         $representante_pc = $rep_id > 0 ? (Utils::userName($rep_id) ?? '') : '';
      }

      $pdf = self::makePdf('pc',
         __('Computer Responsibility - ', 'responsivas') . $full_name,
         'Responsiva de computadora',
         'responsiva, computadora, activos, TI',
         $location,
         Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone']),
         $config, 30.0
      );

      $full_name_safe = Utils::escape($full_name);

      foreach ($computers as $comp) {
         $pdf->AddPage();
         $page = $pdf->getPage();
         $pdf->setQrForPage($page, $CFG_GLPI['url_base'] . '/front/computer.form.php?id=' . $comp->getID());

         $marca         = Utils::escape(Utils::dropdownName($comp->fields['manufacturers_id'], 'glpi_manufacturers'));
         $modelo        = Utils::escape(Utils::dropdownName($comp->fields['computermodels_id'], 'glpi_computermodels'));
         $serie         = Utils::escape($comp->fields['serial'] ?: 'N/A');
         $activo        = Utils::escape($comp->fields['otherserial'] ?: 'N/A');
         $comentarios   = Utils::escape($comp->fields['comment'] ?: __('No comments', 'responsivas'));
         $tipo          = Utils::escape(Utils::dropdownName($comp->fields['computertypes_id'], 'glpi_computertypes', __('Not specified', 'responsivas')));
         $estado_nombre = Utils::escape(Utils::dropdownName($comp->fields['states_id'], 'glpi_states'));

         // CPU
         $cpu_name = __('Not specified', 'responsivas');
         $cpu_freq = __('Not specified', 'responsivas');
         foreach ((new \Item_DeviceProcessor())->find(['items_id' => $comp->getID(), 'itemtype' => 'Computer'], ['id DESC'], 1) as $row) {
            $device = new \DeviceProcessor();
            if ($device->getFromDB($row['deviceprocessors_id'])) {
               $mfr      = Utils::dropdownName($device->fields['manufacturers_id'], 'glpi_manufacturers', '');
               $cpu_name = Utils::escape(trim($device->fields['designation'] ? $mfr . ' ' . $device->fields['designation'] : $cpu_name));
               $cpu_freq = !empty($device->fields['frequence'])
                  ? Utils::escape(number_format($device->fields['frequence'] / 1000, 2) . ' GHz')
                  : $cpu_freq;
            }
         }

         // RAM
         $ram_parts = [];
         foreach ((new \Item_DeviceMemory())->find(['items_id' => $comp->getID(), 'itemtype' => 'Computer', 'is_deleted' => 0]) as $row) {
            $device = new \DeviceMemory();
            if ($device->getFromDB($row['devicememories_id']) && $device->fields['designation']) {
               $ram_parts[] = $device->fields['designation'];
            }
         }
         $ram_texto = Utils::escape($ram_parts ? implode(' + ', $ram_parts) : __('Not specified', 'responsivas'));

         // SO
         $os_texto = __('Not specified', 'responsivas');
         foreach ((new \Item_OperatingSystem())->find(['items_id' => $comp->getID(), 'itemtype' => 'Computer', 'is_deleted' => 0], ['date_mod DESC'], 1) as $row) {
            $partes = [];
            if ($so = Utils::dropdownName($row['operatingsystems_id'] ?? 0, 'glpi_operatingsystems', ''))         $partes[] = $so;
            if ($v  = Utils::dropdownName($row['operatingsystemversions_id'] ?? 0, 'glpi_operatingsystemversions', '')) $partes[] = $v;
            if ($ed = Utils::dropdownName($row['operatingsystemeditions_id'] ?? 0, 'glpi_operatingsystemeditions', '')) $partes[] = $ed;
            if ($partes) $os_texto = Utils::escape(implode(' ', $partes));
         }

         // Disco
         $disk_names = [];
         foreach ((new \Item_DeviceHardDrive())->find(['items_id' => $comp->getID(), 'itemtype' => 'Computer', 'is_deleted' => 0]) as $row) {
            $device = new \DeviceHardDrive();
            if ($device->getFromDB($row['deviceharddrives_id']) && $device->fields['designation']) {
               $disk_names[] = $device->fields['designation'];
            }
         }
         $disco = $disk_names ? Utils::escape(implode(', ', array_unique($disk_names))) : __('Not specified', 'responsivas');

         // Periféricos (monitores y accesorios)
         $dispositivos_html    = '';
         $printed_header_devs  = false;

         $result = $DB->request([
            'SELECT'     => ['glpi_monitors.id', 'glpi_monitors.serial', 'glpi_monitors.otherserial', 'glpi_monitors.states_id', 'glpi_monitors.manufacturers_id', 'glpi_monitors.monitormodels_id'],
            'FROM'       => 'glpi_assets_assets_peripheralassets',
            'INNER JOIN' => ['glpi_monitors' => ['ON' => ['glpi_assets_assets_peripheralassets' => 'items_id_peripheral', 'glpi_monitors' => 'id']]],
            'WHERE'      => ['glpi_assets_assets_peripheralassets.itemtype_asset' => 'Computer', 'glpi_assets_assets_peripheralassets.items_id_asset' => $comp->getID(), 'glpi_assets_assets_peripheralassets.itemtype_peripheral' => 'Monitor', 'glpi_assets_assets_peripheralassets.is_deleted' => 0, 'glpi_monitors.users_id' => $user_id],
         ]);
         foreach ($result as $row) {
            if (self::loadViewableItem(new \Monitor(), (int) ($row['id'] ?? 0)) === null) {
               continue;
            }
            self::appendDevicesHeader($dispositivos_html, $printed_header_devs, $th_bg);
            self::appendDeviceRow(
               $dispositivos_html,
               __('Monitor', 'responsivas'),
               (string)Utils::dropdownName($row['manufacturers_id'], 'glpi_manufacturers'),
               (string)Utils::dropdownName($row['monitormodels_id'], 'glpi_monitormodels'),
               (string)($row['serial'] ?: 'N/A'),
               (string)($row['otherserial'] ?: 'N/A'),
               (string)Utils::dropdownName($row['states_id'], 'glpi_states'),
               $td_bg
            );
         }

         $result = $DB->request([
            'SELECT'     => ['glpi_peripherals.id', 'glpi_peripherals.name', 'glpi_peripherals.serial', 'glpi_peripherals.otherserial', 'glpi_peripherals.states_id', 'glpi_peripherals.manufacturers_id', 'glpi_peripheraltypes.name AS tipo', 'glpi_peripheralmodels.name AS modelo'],
            'FROM'       => 'glpi_assets_assets_peripheralassets',
            'INNER JOIN' => [
               'glpi_peripherals'     => ['ON' => ['glpi_assets_assets_peripheralassets' => 'items_id_peripheral', 'glpi_peripherals' => 'id']],
               'glpi_peripheraltypes' => ['ON' => ['glpi_peripherals' => 'peripheraltypes_id', 'glpi_peripheraltypes' => 'id']],
            ],
            'LEFT JOIN'  => ['glpi_peripheralmodels' => ['ON' => ['glpi_peripherals' => 'peripheralmodels_id', 'glpi_peripheralmodels' => 'id']]],
            'WHERE'      => ['glpi_assets_assets_peripheralassets.itemtype_asset' => 'Computer', 'glpi_assets_assets_peripheralassets.items_id_asset' => $comp->getID(), 'glpi_assets_assets_peripheralassets.itemtype_peripheral' => 'Peripheral', 'glpi_assets_assets_peripheralassets.is_deleted' => 0, 'glpi_peripherals.users_id' => $user_id],
         ]);
         foreach ($result as $row) {
            if (self::loadViewableItem(new \Peripheral(), (int) ($row['id'] ?? 0)) === null) {
               continue;
            }
            self::appendDevicesHeader($dispositivos_html, $printed_header_devs, $th_bg);
            self::appendDeviceRow(
               $dispositivos_html,
               (string)($row['tipo'] ?? 'N/A'),
               (string)Utils::dropdownName($row['manufacturers_id'], 'glpi_manufacturers'),
               (string)($row['modelo'] ?? 'N/A'),
               (string)($row['serial'] ?: 'N/A'),
               (string)($row['otherserial'] ?: 'N/A'),
               (string)Utils::dropdownName($row['states_id'], 'glpi_states'),
               $td_bg
            );
         }

         $employee_line_html = $employee_line ? "<br>{$employee_line}" : '';

         // ── Plantillas editables ──
         // Purchase price (same Infocom source/format as phone responsibilities).
         $pc_precio_compra = 'N/A';
         $pc_infocoms = (new \Infocom())->find([
            'itemtype' => 'Computer',
            'items_id' => $comp->getID(),
         ], [], 1);
         if (!empty($pc_infocoms)) {
            $pc_infocom = reset($pc_infocoms);
            if (isset($pc_infocom['value']) && is_numeric($pc_infocom['value']) && (float)$pc_infocom['value'] > 0) {
               $currency = !empty($config['currency']) ? $config['currency'] : '$';
               $pc_precio_compra = $currency . number_format((float)$pc_infocom['value'], 2, '.', ',');
            }
         }

         $pc_vars = [
            '{nombre}'      => Utils::escape($full_name),
            '{empresa}'     => $company_name,
            '{num_empleado}'=> $employee_number,
            '{activo}'      => $activo,
            '{serie}'       => $serie,
            '{marca}'       => $marca,
            '{modelo}'      => $modelo,
            '{tipo}'        => $tipo,
            '{estado}'      => $estado_nombre,
            '{fecha}'       => Utils::escape(Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone'])),
            '{lugar}'       => $location,
            '{precio}'      => Utils::escape($pc_precio_compra),
         ];
                  // Useful life paragraph: same invoice/no-invoice selection as phones.
         // Unlike phones, this is a paragraph in the existing computer body, not a new numbered clause.
         $infocoms = (new \Infocom())->find([
            'itemtype' => 'Computer',
            'items_id' => $comp->getID(),
         ], [], 1);
         $factura      = 'N/A';
         $fecha_compra = 'N/A';
         $proveedor    = 'N/A';

         if (!empty($infocoms)) {
            $infocom = reset($infocoms);
            if (!empty(trim($infocom['bill'] ?? ''))) {
               $factura = trim($infocom['bill']);
            }
            if (!empty($infocom['buy_date']) && $infocom['buy_date'] !== '0000-00-00') {
               $fecha_compra = Utils::dateToText($infocom['buy_date'], $config['timezone']);
            }
            $proveedor = Utils::dropdownName($infocom['suppliers_id'] ?? 0, 'glpi_suppliers');
            if ($proveedor === '') {
               $proveedor = 'N/A';
            }
         }

         $pc_vu_vars = [
            '{fecha_compra}' => $fecha_compra !== 'N/A' ? Utils::escape($fecha_compra) : '',
            '{factura}'      => Utils::escape($factura),
            '{proveedor}'    => Utils::escape($proveedor),
            '{precio}'       => Utils::escape($pc_precio_compra),
         ];

         $clausula_vida_util_text = '';
         if ($factura !== 'N/A' && $proveedor !== 'N/A') {
            $pc_vu_tpl = trim($config['pc_vida_util_factura'] ?? '');
            if ($pc_vu_tpl !== '') {
               $clausula_vida_util_text = Utils::applyTemplate($pc_vu_tpl, $pc_vu_vars) . '<br>';
            }
         } else {
            $pc_vu_tpl = trim($config['pc_vida_util_sin'] ?? '');
            if ($pc_vu_tpl !== '') {
               $clausula_vida_util_text = Utils::applyTemplate($pc_vu_tpl, $pc_vu_vars) . '<br>';
            }
         }

         $pc_vars['{fecha_compra}']        = $pc_vu_vars['{fecha_compra}'];
         $pc_vars['{factura}']             = $pc_vu_vars['{factura}'];
         $pc_vars['{proveedor}']           = $pc_vu_vars['{proveedor}'];
         $pc_vars['{clausula_vida_util}']  = $clausula_vida_util_text;

         $pc_titulo = Utils::applyTemplate($config['pc_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO', $pc_vars);
         $pc_intro  = Utils::applyTemplate($config['pc_intro']  ?? '', $pc_vars);
         $pc_cuerpo_template = $config['pc_cuerpo'] ?? '';
         if (!str_contains($pc_cuerpo_template, '{clausula_vida_util}') && $clausula_vida_util_text !== '') {
            // Existing saved templates may not have the marker yet.
            // Keep the paragraph in the same position as the computer template default.
            $pc_cuerpo_template = str_replace(
               "Cuando se goce de periodo vacacional",
               "{clausula_vida_util}

Cuando se goce de periodo vacacional",
               $pc_cuerpo_template
            );
         }
         $pc_cuerpo = Utils::renderTemplate(Utils::applyTemplate($pc_cuerpo_template, $pc_vars));

         $pdf->writeHTML(self::renderPcPage(
            $pc_titulo, $pc_intro, $pc_cuerpo,
            $marca, $modelo, $serie, $cpu_name, $cpu_freq,
            $activo, Utils::escape((string)($comp->fields['name'] ?? '')), $ram_texto, $os_texto, $disco, $tipo, $estado_nombre,
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

      $config = \Config::getConfigurationValues('plugin_responsivas');
      self::validateTemplates('pri', $config);

      $user = new \User();
      if (!$user->getFromDB($user_id) || !$user->canView()) {
         throw new \RuntimeException(__('User not found.', 'responsivas'));
      }

      $printers = self::filterViewableRows(\Printer::class, (new \Printer())->find(['users_id' => $user_id, 'is_deleted' => 0]));
      if (empty($printers)) {
         throw new \RuntimeException(__('The user has no assigned equipment.', 'responsivas'));
      }

      $entity = new \Entity();
      if (!$entity->getFromDB(\Session::getActiveEntity())) {
         throw new \RuntimeException(__('Could not retrieve the entity.', 'responsivas'));
      }

      $location = self::getActiveEntityLocation();

      $full_name       = $user->getFriendlyName();
      $employee_number = !empty($user->fields['registration_number']) ? $user->fields['registration_number'] : '';
      $show_employee   = (int)($config['show_employee_number'] ?? 0);
      $company_name    = Utils::escape($config['company_name'] ?? '');
      $employee_line   = ($show_employee && $employee_number) ? (__("Employee No.: ", "responsivas") . Utils::escape($employee_number)) : '';

      $th_bg = '#E6E6E6';
      $td_bg = '#FFFFFF';

      $show_both_sigs_pri = (int)($config['pri_show_comodato_sigs'] ?? 0) === 1;
      $representante_pri  = '';
      if ($show_both_sigs_pri) {
         $rep_id = (int)($config['representante'] ?? 0);
         $representante_pri = $rep_id > 0 ? (Utils::userName($rep_id) ?? '') : '';
      }

      $pdf = self::makePdf('pri',
         __('Printer Responsibility - ', 'responsivas') . $full_name,
         'Responsiva de impresora',
         'responsiva, impresora, activos, TI',
         $location,
         Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone']),
         $config, 40.0
      );
      $pdf->AddPage();

      $i          = 0;
      $full_safe  = Utils::escape($full_name);

      // ── Plantillas editables ──
      $pri_base_vars = [
         '{nombre}'       => Utils::escape($full_name),
         '{empresa}'      => $company_name,
         '{num_empleado}' => Utils::escape($employee_number),
         '{fecha}'        => Utils::escape(Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone'])),
         '{lugar}'        => $location,
      ];

      foreach ($printers as $printer) {
         if ($i > 0) {
            $pdf->AddPage();
         }
         $page      = $pdf->getPage();
         $asset_url = $CFG_GLPI['url_base'] . '/front/printer.form.php?id=' . (int)$printer['id'];
         $pdf->setQrForPage($page, $asset_url);

         $marca         = Utils::escape(Utils::dropdownName($printer['manufacturers_id'] ?? 0, 'glpi_manufacturers', __('Not specified', 'responsivas')));
         $modelo        = Utils::escape(Utils::dropdownName($printer['printermodels_id'] ?? 0, 'glpi_printermodels', __('Not specified', 'responsivas')));
         $tipo          = Utils::escape(Utils::dropdownName($printer['printertypes_id'] ?? 0, 'glpi_printertypes', __('Not specified', 'responsivas')));
         $estado_nombre = Utils::escape(Utils::dropdownName($printer['states_id'] ?? 0, 'glpi_states'));
         $serie         = Utils::escape(!empty($printer['serial'])      ? $printer['serial']      : 'N/A');
         $activo        = Utils::escape(!empty($printer['otherserial']) ? $printer['otherserial'] : 'N/A');
         $comentarios   = Utils::escape(!empty($printer['comment'])     ? $printer['comment']     : __('No comments', 'responsivas'));

         $pri_vars = array_merge($pri_base_vars, [
            '{activo}'  => $activo,
            '{serie}'   => $serie,
            '{marca}'   => $marca,
            '{modelo}'  => $modelo,
            '{tipo}'    => $tipo,
            '{estado}'  => $estado_nombre,
         ]);
         $pri_titulo = Utils::applyTemplate($config['pri_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO', $pri_vars);
         $pri_intro  = Utils::applyTemplate($config['pri_intro']  ?? '', $pri_vars);
         $pri_cuerpo = Utils::renderTemplate(Utils::applyTemplate($config['pri_cuerpo'] ?? '', $pri_vars));

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

      $config = \Config::getConfigurationValues('plugin_responsivas');
      self::validateTemplates('pho', $config);

      $user = new \User();
      if (!$user->getFromDB($user_id) || !$user->canView()) {
         throw new \RuntimeException(__('User not found.', 'responsivas'));
      }

      $cellphone_type_id = (int)($config['cellphone_type_id'] ?? 0);
      if ($cellphone_type_id <= 0) {
         throw new \RuntimeException(__('The phone type for loan agreements is not configured in the plugin.', 'responsivas'));
      }

      $phones = self::filterViewableRows(\Phone::class, (new \Phone())->find([
         'users_id'      => $user_id,
         'is_deleted'    => 0,
         'phonetypes_id' => $cellphone_type_id,
      ]));
      if (empty($phones)) {
         throw new \RuntimeException(__('The user has no phones of the configured type assigned.', 'responsivas'));
      }

      $entity = new \Entity();
      if (!$entity->getFromDB(\Session::getActiveEntity())) {
         throw new \RuntimeException(__('Could not retrieve the entity.', 'responsivas'));
      }

      $address  = Utils::escape($entity->fields['address']  ?? '');
      $postcode = Utils::escape($entity->fields['postcode'] ?? '');
      $location = self::getActiveEntityLocation();

      $full_name       = $user->getFriendlyName();
      $employee_number = $user->fields['registration_number'] ?? '';

      $testigo1_id      = (int)($config['testigo_1']     ?? 0);
      $testigo2_id      = (int)($config['testigo_2']     ?? 0);
      $representante_id = (int)($config['representante'] ?? 0);

      if ($testigo1_id <= 0 || $testigo2_id <= 0) {
         throw new \RuntimeException(__('You must configure Witness 1 and Witness 2 in the plugin settings.', 'responsivas'));
      }
      if ($representante_id <= 0) {
         throw new \RuntimeException(__('You must configure the legal representative in the plugin settings.', 'responsivas'));
      }

      $testigo1_nombre      = Utils::userName($testigo1_id);
      $testigo2_nombre      = Utils::userName($testigo2_id);
      $representante_nombre = Utils::userName($representante_id);

      if (!$testigo1_nombre || !$testigo2_nombre) {
         throw new \RuntimeException(__('One or both configured witnesses are invalid or inactive.', 'responsivas'));
      }
      if (!$representante_nombre) {
         throw new \RuntimeException(__('The legal representative is invalid or inactive.', 'responsivas'));
      }

      $show_employee = (int)($config['show_employee_number'] ?? 1);
      $company_name  = Utils::escape($config['company_name'] ?? '');
      $emp_safe      = Utils::escape($employee_number);
      $employee_line = ($show_employee && !empty($emp_safe)) ? (__("Employee No.: ", "responsivas") . $emp_safe) : '';

      // Pre-validar precios ANTES de crear el PDF
      foreach ($phones as $phone) {
         $infocoms_check = (new \Infocom())->find(['itemtype' => 'Phone', 'items_id' => (int)$phone['id']], [], 1);
         $precio_check   = 0.0;
         if (!empty($infocoms_check)) {
            $ic = reset($infocoms_check);
            if (isset($ic['value']) && is_numeric($ic['value'])) {
               $precio_check = (float)$ic['value'];
            }
         }
         if ($precio_check <= 0) {
            $nombre_tel    = trim(Utils::dropdownName($phone['manufacturers_id'] ?? 0, 'glpi_manufacturers', '') . ' ' . Utils::dropdownName($phone['phonemodels_id'] ?? 0, 'glpi_phonemodels', ''));
            $nombre_activo = trim($phone['name'] ?? '');
            $identificador = $nombre_activo !== '' ? $nombre_activo . ($nombre_tel !== '' ? " ({$nombre_tel})" : '') : ($nombre_tel ?: 'IMEI: ' . ($phone['serial'] ?? 'N/A'));
            throw new \RuntimeException(sprintf(
               __('Phone "%s" has no purchase price. Add it in Management → Administrative and financial information → Purchase price.', 'responsivas'),
               $identificador
            ));
         }
      }

      // Pre-validar líneas ANTES de crear el PDF
      foreach ($phones as $phone) {
         if (empty((new \Item_Line())->find(['itemtype' => 'Phone', 'items_id' => (int)$phone['id']], [], 1))) {
            $nombre_tel    = trim(Utils::dropdownName($phone['manufacturers_id'] ?? 0, 'glpi_manufacturers', '') . ' ' . Utils::dropdownName($phone['phonemodels_id'] ?? 0, 'glpi_phonemodels', ''));
            $nombre_activo = trim($phone['name'] ?? '');
            $identificador = $nombre_activo !== '' ? $nombre_activo . ($nombre_tel !== '' ? " ({$nombre_tel})" : '') : ($nombre_tel ?: 'IMEI: ' . ($phone['serial'] ?? 'N/A'));
            throw new \RuntimeException(sprintf(
               __('Phone "%s" has no line assigned. Assign a line in the phone\'s record before generating the PDF.', 'responsivas'),
               $identificador
            ));
         }
      }

      $currency    = !empty($config['currency']) ? $config['currency'] : '$';
      $dt          = new \DateTime('now', new \DateTimeZone($config['timezone']));
      $hora_texto  = $dt->format('H') . ':00';
      $fecha_texto = Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone']);

      $pdf = self::makePdf('pho',
         __('Phone Loan - ', 'responsivas') . $full_name,
         'Comodato de teléfono',
         'comodato, teléfono, activos, TI',
         $location,
         $fecha_texto,
         $config, 25.0
      );

      $full_name_safe = Utils::escape($full_name);

      foreach ($phones as $phone) {
         $pdf->AddPage();
         $page      = $pdf->getPage();
         $asset_url = $CFG_GLPI['url_base'] . '/front/phone.form.php?id=' . (int)$phone['id'];
         $pdf->setQrForPage($page, $asset_url);

         $marca  = Utils::dropdownName($phone['manufacturers_id'] ?? 0, 'glpi_manufacturers', __('Not specified', 'responsivas'));
         $modelo = Utils::dropdownName($phone['phonemodels_id'] ?? 0, 'glpi_phonemodels', __('Not specified', 'responsivas'));
         $imei   = $phone['serial'] ?? 'N/A';
         $activo = $phone['otherserial'] ?? 'N/A';
         $serie  = $phone['uuid'] ?? 'N/A';
         $item_lines = (new \Item_Line())->find(['itemtype' => 'Phone', 'items_id' => (int)$phone['id']], [], 1);
         $line_row   = reset($item_lines);
         $line_obj   = new \Line();
         $linea      = 'N/A';
         if ($line_obj->getFromDB((int)$line_row['lines_id'])) {
            $linea = !empty($line_obj->fields['caller_num'])
               ? $line_obj->fields['caller_num']
               : ($line_obj->fields['name'] ?? 'N/A');
         }
         $estado = Utils::dropdownName($phone['states_id'] ?? 0, 'glpi_states');

         // Infocoms
         $infocoms          = (new \Infocom())->find(['itemtype' => 'Phone', 'items_id' => (int)$phone['id']], [], 1);
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
               $fecha_compra = Utils::dateToText($infocom['buy_date'], $config['timezone']);
            }
            $proveedor = Utils::dropdownName($infocom['suppliers_id'] ?? 0, 'glpi_suppliers');
         }

         // RAM
         $ram_parts = [];
         foreach ((new \Item_DeviceMemory())->find(['items_id' => (int)$phone['id'], 'itemtype' => 'Phone', 'is_deleted' => 0]) as $mem) {
            if (!empty($mem['devicememories_id'])) {
               $des = Utils::dropdownName((int)$mem['devicememories_id'], 'glpi_devicememories');
               if (!empty($des)) $ram_parts[] = $des;
            }
         }
         $ram_texto = !empty($ram_parts) ? implode(' + ', $ram_parts) : __('Not specified', 'responsivas');

         // Disco
         $disco    = __('Not specified', 'responsivas');
         $total_mb = 0;
         $nombres  = [];
         foreach ((new \Item_DeviceHardDrive())->find(['items_id' => (int)$phone['id'], 'itemtype' => 'Phone', 'is_deleted' => 0]) as $row) {
            if (!empty($row['capacity']) && is_numeric($row['capacity'])) $total_mb += (int)$row['capacity'];
            if (!empty($row['deviceharddrives_id'])) {
               $n = Utils::dropdownName((int)$row['deviceharddrives_id'], 'glpi_deviceharddrives');
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
         $marca    = Utils::escape($marca);
         $modelo   = Utils::escape($modelo);
         $serie    = Utils::escape($serie);
         $activo   = Utils::escape($activo);
         $imei     = Utils::escape($imei);
         $linea    = Utils::escape($linea);
         $estado   = Utils::escape($estado);

         // ── Plantillas editables ──
         // Cláusula de vida útil — usa plantilla configurable, cae a texto predeterminado si vacío
         $vu_vars = [
            '{fecha_compra}' => $fecha_compra !== 'N/A' ? Utils::escape($fecha_compra) : '',
            '{factura}'      => Utils::escape($factura),
            '{proveedor}'    => Utils::escape($proveedor),
         ];
         if ($factura !== 'N/A' && $proveedor !== 'N/A') {
            $vu_tpl = trim($config['pho_vida_util_factura'] ?? '');
            if ($vu_tpl !== '') {
               $clausula_vida_util_text = Utils::applyTemplate($vu_tpl, $vu_vars);
            } else {
               $partes_cu = [];
               if ($fecha_compra !== 'N/A') $partes_cu[] = 'contados a partir del ' . Utils::escape($fecha_compra);
               $partes_cu[]             = 'con base en la factura ' . Utils::escape($factura) . ' emitida por ' . Utils::escape($proveedor);
               $clausula_vida_util_text = 'Se establece como <strong>vida útil</strong> un periodo de 24 meses ' . implode(', ', $partes_cu) . '.';
            }
         } else {
            $vu_tpl = trim($config['pho_vida_util_sin'] ?? '');
            $clausula_vida_util_text = $vu_tpl !== ''
               ? Utils::applyTemplate($vu_tpl, $vu_vars)
               : 'Se establece como <strong>vida útil</strong> un periodo de 24 meses desde la fecha de asignación.';
         }

         $pho_vars = [
            '{nombre}'            => Utils::escape($full_name),
            '{empresa}'           => $company_name,
            '{num_empleado}'      => Utils::escape($employee_number),
            '{activo}'            => $activo,
            '{serie_uuid}'        => $serie,
            '{imei}'              => $imei,
            '{marca}'             => $marca,
            '{modelo}'            => $modelo,
            '{estado}'            => $estado,
            '{precio}'            => Utils::escape($precio_compra),
            '{linea}'             => $linea,
            '{ram}'               => Utils::escape($ram_texto),
            '{almacenamiento}'    => Utils::escape($disco),
            '{fecha}'             => Utils::escape($fecha_texto),
            '{hora}'              => Utils::escape($hora_texto),
            '{lugar}'             => $location,
            '{direccion}'         => $address,
            '{cp}'                => $postcode,
            '{representante}'     => $representante_nombre,
            '{testigo1}'          => $testigo1_nombre,
            '{testigo2}'          => $testigo2_nombre,
            '{clausula_vida_util}'=> $clausula_vida_util_text,
         ];
         $pho_titulo    = Utils::applyTemplate($config['pho_titulo'] ?? 'CONTRATO DE COMODATO', $pho_vars);
         $pho_apertura  = Utils::applyTemplate($config['pho_apertura']  ?? '', $pho_vars);
         $pho_clausulas = Utils::renderTemplate(Utils::applyTemplate($config['pho_clausulas'] ?? '', $pho_vars));
         $pho_testigos  = Utils::applyTemplate($config['pho_testigos']  ?? '', $pho_vars);

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
    * Crea y configura un PDF con todos
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
   ): PDF {
      $font_key = match ($type) { 'pc' => 'pc_font_size', 'pri' => 'pri_font_size', 'pho' => 'pho_font_size' };
      $creator  = self::getCreator();

      $pdf = new PDF('P', 'mm', 'LETTER');
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
      $pdf->SetFont(\Config::getConfigurationValue('core', 'pdffont'), '', (int)($config[$font_key] ?? 10));
      return $pdf;
   }

   private static function renderPcPage(
      string $titulo, string $intro, string $cuerpo,
      string $marca, string $modelo, string $serie,
      string $cpu_name, string $cpu_freq,
      string $activo, string $identificacion,
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
         ? '<br><br><br><br><table nobr="true" width="100%" style="text-align:center;">'
           . '<tr>'
           . '<td width="50%"><strong>' . $l['lender'] . '</strong><br><br>_______________________________<br>' . $representante . '</td>'
           . '<td width="50%"><strong>' . $l['borrower'] . '</strong><br><br>_______________________________<br>' . $full_name_safe . $employee_line_html . '</td>'
           . '</tr></table>'
         : '<br><br><br><br><table nobr="true" width="100%" style="text-align:center;">'
           . '<tr><td><strong>_________________________________<br>' . $full_name_safe . $employee_line_html . '</strong></td></tr>'
           . '</table>';

      $asset_table = self::renderComputerAssetTable(
         $marca, $modelo, $serie, $cpu_name, $cpu_freq, $activo, $identificacion, $ram, $os, $disco, $tipo, $estado,
         $comentarios, $dispositivos_html, $th_bg, $td_bg
      );

      return <<<HTML
<h2 style="text-align:center;">{$titulo}</h2>
<table nobr="true" width="100%"><tr><td style="text-align:justify;line-height:1.2;">{$intro}</td></tr></table>
{$asset_table}
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
         ? '<br><br><br><br><table nobr="true" width="100%" style="text-align:center;">'
           . '<tr>'
           . '<td width="50%"><strong>' . $l['lender'] . '</strong><br><br>_______________________________<br>' . $representante . '</td>'
           . '<td width="50%"><strong>' . $l['borrower'] . '</strong><br><br>_______________________________<br>' . $full_safe . $emp_sep . '</td>'
           . '</tr></table>'
         : '<br><br><br><br><table nobr="true" width="100%" style="text-align:center;">'
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
      $user = new \User();
      if (!$user->getFromDB($user_id) || !$user->canView()) {
         throw new \RuntimeException(__('User not found.', 'responsivas'));
      }

      // The preview must never hide incomplete entity location data with demo
      // values; validate it before both real and demo preview generation.
      self::getActiveEntityLocation();

      // ── Intentar con activos reales ──────────────────────────────────
      // Primero comprobamos si el usuario tiene activos del tipo solicitado
      // antes de llamar a buildXxxPdf (que lanza excepción si no hay activos
      // pero también si la plantilla está vacía o faltan datos de entidad)
      $has_assets = match ($type) {
         'pc'  => !empty(self::filterViewableRows(\Computer::class, (new \Computer())->find(['users_id' => $user_id, 'is_deleted' => 0]))),
         'pri' => !empty(self::filterViewableRows(\Printer::class, (new \Printer())->find(['users_id' => $user_id, 'is_deleted' => 0]))),
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
            PDF::$global_watermark      = true;
            PDF::$global_watermark_text = $wm_text !== '' ? $wm_text : __('PREVIEW', 'responsivas');
            try {
               $result = self::$method($user_id);
            } finally {
               PDF::$global_watermark = false;
               PDF::$global_watermark_text = 'PREVIEW';
            }

            // The preview watermark must still be available when TCPDF renders
            // Header/Footer during Output(). Do not rely on the temporary
            // static flag above because it is intentionally reset before the
            // response is generated. Keep the watermark on this PDF instance.
            $wm_text = trim($config['watermark_text'] ?? '');
            $result['pdf']->show_watermark    = true;
            $result['pdf']->watermark_text    = $wm_text !== '' ? $wm_text : __('PREVIEW', 'responsivas');
            $result['pdf']->watermark_opacity = max(5, min(100, (int)($config['watermark_opacity'] ?? 25)));

            $result['pdf']->SetTitle(match ($type) {
               'pc'  => __('Preview - Computer Responsibility - ', 'responsivas') . $user->getFriendlyName(),
               'pri' => __('Preview - Printer Responsibility - ',  'responsivas') . $user->getFriendlyName(),
               'pho' => __('Preview - Phone Loan - ',              'responsivas') . $user->getFriendlyName(),
            });
            return $result;
         } catch (\RuntimeException) {
            // Caída silenciosa solo en errores esperados (activos faltantes, plantilla vacía) → usar demo
         }
      }

      // ── Sin activos o error → construir PDF demo ─────────────────────
      return self::buildDemoPdf($type, $user, $config);
   }

   /** Verifica si el usuario tiene teléfonos del tipo configurado */
   private static function userHasPhones(int $user_id, array $config): bool
   {
      $type_id = (int)($config['cellphone_type_id'] ?? 0);
      if ($type_id === 0) {
         return false;
      }
      $rows = (new \Phone())->find([
         'users_id' => $user_id,
         'phonetypes_id' => $type_id,
         'is_deleted' => 0,
      ]);
      return !empty(self::filterViewableRows(\Phone::class, $rows));
   }

   /* =====================================================
    * PDF completamente demo (sin datos reales de GLPI)
    * ===================================================== */
   private static function buildDemoPdf(string $type, \User $user, array $config): array
   {
      global $CFG_GLPI;

      $full_name         = $user->getFriendlyName();
      $full_name_safe    = Utils::escape($full_name);
      $company_name      = Utils::escape($config['company_name'] ?? 'Mi Empresa');
      $real_employee     = trim($user->fields['registration_number'] ?? '');
      $demo_emp          = $real_employee !== '' ? Utils::escape($real_employee) : 'EMP-001';
      $show_employee     = (int)($config['show_employee_number'] ?? 0);
      $th_bg             = '#E6E6E6';
      $td_bg             = '#FFFFFF';

      // Ubicación desde la entidad activa, igual que los documentos reales.
// No usar una ciudad/estado hardcodeados en el preview.
      $location = self::getActiveEntityLocation();

      // Testigos / representante reales si están configurados, si no → demo
      $t1_id  = (int)($config['testigo_1']     ?? 0);
      $t2_id  = (int)($config['testigo_2']     ?? 0);
      $rep_id = (int)($config['representante'] ?? 0);
      $testigo1 = ($t1_id  > 0 && ($n = Utils::userName($t1_id))  !== '') ? $n : __('Demo Witness 1',     'responsivas');
      $testigo2 = ($t2_id  > 0 && ($n = Utils::userName($t2_id))  !== '') ? $n : __('Demo Witness 2',     'responsivas');
      $rep      = ($rep_id > 0 && ($n = Utils::userName($rep_id)) !== '') ? $n : __('Demo Representative', 'responsivas');

      // Estado al azar de los existentes en GLPI
      global $DB;
      $states     = iterator_to_array($DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_states', 'LIMIT' => 10]));
      $demo_state = !empty($states) ? Utils::escape(reset($states)['name']) : 'En uso';

      $fecha_header = Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone']);

      // ── Computadora ─────────────────────────────────────────────────
      if ($type === 'pc') {
         $pdf = self::makePdf('pc',
            __('Preview - Computer Responsibility - ', 'responsivas') . $full_name,
            'Vista previa responsiva de computadora',
            'vista previa, responsiva, computadora, activos, TI',
            $location, $fecha_header, $config, 18.0, true
         );
         $pdf->AddPage();
         $pdf->setQrForPage($pdf->getPage(), $CFG_GLPI['url_base']);

         $employee_line      = ($show_employee && $demo_emp) ? (__("Employee No.: ", "responsivas") . $demo_emp) : '';
         $employee_line_html = $employee_line ? "<br>{$employee_line}" : '';

         $pc_vars = [
            '{nombre}' => Utils::escape($full_name), '{empresa}' => $company_name,
            '{num_empleado}' => $demo_emp,   '{activo}' => 'PC-DEMO-001',
            '{serie}' => 'SN-DEMO-123456',   '{marca}'  => 'Dell',
            '{modelo}' => 'Latitude 5540',   '{tipo}'   => 'Laptop',
            '{estado}' => $demo_state,
            '{fecha}' => Utils::escape(Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone'])),
            '{lugar}' => $location,
            '{precio}' => '$ 15,000.00',
         ];
                  // Preview uses the configured invoice clause only when it has content.
         $pc_vu_demo_vars = [
            '{fecha_compra}' => Utils::escape(Utils::dateToText('2026-01-01', $config['timezone'])),
            '{factura}'      => 'DEMO-FACT-001',
            '{proveedor}'    => 'Proveedor demo',
            '{precio}'       => '$ 15,000.00',
         ];
         $pc_vu_demo_tpl = trim($config['pc_vida_util_factura'] ?? '');
         $clausula_vida_util_demo = $pc_vu_demo_tpl !== ''
            ? Utils::applyTemplate($pc_vu_demo_tpl, $pc_vu_demo_vars) . '<br>'
            : Utils::applyTemplate(
               'Se establece como <strong>vida útil</strong> un periodo de 24 meses contados a partir del {fecha_compra}, con base en la factura {factura} emitida por {proveedor}.',
               $pc_vu_demo_vars
            ) . '<br>';

         $pc_vars['{fecha_compra}']       = $pc_vu_demo_vars['{fecha_compra}'];
         $pc_vars['{factura}']            = $pc_vu_demo_vars['{factura}'];
         $pc_vars['{proveedor}']          = $pc_vu_demo_vars['{proveedor}'];
         $pc_vars['{clausula_vida_util}'] = $clausula_vida_util_demo;

         $pc_titulo = Utils::applyTemplate($config['pc_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO', $pc_vars);
         $pc_intro  = Utils::applyTemplate($config['pc_intro']  ?? '', $pc_vars);
         $pc_cuerpo_template = $config['pc_cuerpo'] ?? '';
         if (!str_contains($pc_cuerpo_template, '{clausula_vida_util}')) {
            // The computer preview always demonstrates the optional paragraph.
            $pc_cuerpo_template = str_replace(
               "Cuando se goce de periodo vacacional",
               "{clausula_vida_util}\n\nCuando se goce de periodo vacacional",
               $pc_cuerpo_template
            );
         }

         $pc_cuerpo = Utils::renderTemplate(Utils::applyTemplate($pc_cuerpo_template, $pc_vars));

         $show_demo_pc_sigs = (int)($config['pc_show_comodato_sigs'] ?? 0) === 1;
         $demo_pc_rep       = $show_demo_pc_sigs ? $rep : '';
         $pdf->writeHTML(self::renderPcPage(
            $pc_titulo, $pc_intro, $pc_cuerpo,
            'Dell', 'Latitude 5540', 'SN-DEMO-123456',
            'Intel Core i5-1345U', '1.60 GHz',
            'PC-DEMO-001', 'Equipo de demostración',
            '16 GB DDR4', 'Windows 11 Pro', 'SSD 512 GB',
            'Laptop', $demo_state,
            Utils::escape(__('Demo equipment for template preview', 'responsivas')),
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
            '{nombre}' => Utils::escape($full_name), '{empresa}' => $company_name,
            '{num_empleado}' => $demo_emp,   '{activo}' => 'IMP-DEMO-001',
            '{serie}' => 'SN-IMP-789012',    '{marca}'  => 'HP',
            '{modelo}' => 'LaserJet Pro M404n', '{tipo}' => 'Impresora',
            '{estado}' => $demo_state,
            '{fecha}' => Utils::escape(Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone'])),
            '{lugar}' => $location,
         ];
         $pri_titulo = Utils::applyTemplate($config['pri_titulo'] ?? 'CARTA RESPONSIVA DE ACTIVO ASIGNADO', $pri_vars);
         $pri_intro  = Utils::applyTemplate($config['pri_intro']  ?? '', $pri_vars);
         $pri_cuerpo = Utils::renderTemplate(Utils::applyTemplate($config['pri_cuerpo'] ?? '', $pri_vars));

         $show_demo_pri_sigs = (int)($config['pri_show_comodato_sigs'] ?? 0) === 1;
         $demo_pri_rep       = $show_demo_pri_sigs ? $rep : '';
         $pdf->writeHTML(self::renderPriPage(
            $pri_titulo, $pri_intro, $pri_cuerpo,
            'HP', 'LaserJet Pro M404n', 'SN-IMP-789012',
            'Impresora', $demo_state,
            Utils::escape(__('Demo printer for template preview', 'responsivas')),
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
         '{nombre}'             => Utils::escape($full_name),    '{empresa}'       => $company_name,
         '{num_empleado}'       => $demo_emp,         '{activo}'        => 'CEL-DEMO-001',
         '{serie_uuid}'         => 'UUID-DEMO-001',   '{imei}'          => '352999DEMO0001',
         '{marca}'              => 'Samsung',          '{modelo}'        => 'Galaxy A54 5G',
         '{estado}'             => $demo_state,
         '{almacenamiento}'     => '128 GB',           '{ram}'           => '6 GB',
         '{linea}'              => '662-100-0001',     '{precio}'        => '$ 7,500.00',
         '{fecha}'              => Utils::escape(Utils::dateToText($_SESSION['glpi_currenttime'], $config['timezone'])),
         '{lugar}'              => $location,
         '{direccion}'          => $address,
         '{cp}'                 => $postcode,
         '{hora}'               => (new \DateTime('now', new \DateTimeZone($config['timezone'])))->format('H') . ':00',
         '{testigo1}'           => Utils::escape($testigo1),       '{testigo2}'      => Utils::escape($testigo2),
         '{representante}'      => Utils::escape($rep),
         '{clausula_vida_util}' => 'Se establece como <strong>vida útil</strong> un periodo de 24 meses desde la fecha de asignación.',
      ];
      $pho_titulo    = Utils::applyTemplate($config['pho_titulo'] ?? 'CONTRATO DE COMODATO', $pho_vars);
      $pho_apertura  = Utils::applyTemplate($config['pho_apertura']  ?? '', $pho_vars);
      $pho_clausulas = Utils::renderTemplate(Utils::applyTemplate($config['pho_clausulas'] ?? '', $pho_vars));
      $pho_testigos  = Utils::applyTemplate($config['pho_testigos']  ?? '', $pho_vars);

      $pdf->writeHTML(self::renderPhoPage(
         $pho_titulo, $pho_apertura, $pho_clausulas, $pho_testigos,
         Utils::escape($rep), $full_name_safe, $employee_line,
         Utils::escape($testigo1), Utils::escape($testigo2)
      ), true, false, true, false, '');
return ['pdf' => $pdf, 'filename' => self::makeFilename('Comodato_Telefono_DEMO', $full_name)];
   }


}