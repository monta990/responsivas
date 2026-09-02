<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas;

class UserTab extends \CommonGLPI {

   /** Cache por request */
   private static array $cache = [];

   static function getTypeName($nb = 0) {
      return __('Responsivas', 'responsivas');
   }

   /* =====================================================
    * ÚNICO punto de acceso a los conteos (UNA VEZ)
    * ===================================================== */
    private static function getCounts(int $user_id): array {
      global $DB;

      if (isset(self::$cache[$user_id])) {
         return self::$cache[$user_id];
      }

      $config            = \Config::getConfigurationValues('plugin_responsivas');
      $cellphone_type_id = (int)($config['cellphone_type_id'] ?? 0);

      $computers = (int)($DB->request([
         'COUNT' => 'total',
         'FROM'  => 'glpi_computers',
         'WHERE' => ['users_id' => $user_id, 'is_deleted' => 0],
      ])->current()['total'] ?? 0);

      $printers = (int)($DB->request([
         'COUNT' => 'total',
         'FROM'  => 'glpi_printers',
         'WHERE' => ['users_id' => $user_id, 'is_deleted' => 0],
      ])->current()['total'] ?? 0);

      $phones = 0;
      if ($cellphone_type_id > 0) {
         $phones = (int)($DB->request([
            'COUNT' => 'total',
            'FROM'  => 'glpi_phones',
            'WHERE' => [
               'users_id'      => $user_id,
               'is_deleted'    => 0,
               'phonetypes_id' => $cellphone_type_id,
            ],
         ])->current()['total'] ?? 0);
      }

      $data = [
         'computers' => $computers,
         'printers'  => $printers,
         'phones'    => $phones,
      ];
      $data['total'] = array_sum($data);

      return self::$cache[$user_id] = $data;
   }

   /* =====================================================
    * TAB (nombre + badge)
    * ===================================================== */
   public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0) {
      if (!$item instanceof \User) {
         return '';
      }

      $counts = self::getCounts($item->getID());

      $label = __('Responsivas', 'responsivas');
      if ($counts['total'] > 0) {
         // Espacio antes del número dentro del badge → strip_tags da "Responsivas 3" en móvil
         return "<span class='d-flex align-items-center'>"
            . "<i class='ti ti-file-text me-2'></i>"
            . $label
            . "<span class='badge badge-secondary ms-1'> "
            . $counts['total']
            . "</span></span>";
      }
      return "<span class='d-flex align-items-center'>"
         . "<i class='ti ti-file-text me-2'></i>"
         . $label
         . "</span>";
   }

   public static function displayTabContentForItem(
      \CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ) {
      if ($item instanceof \User) {
         self::showTab($item);
      }
      return true;
   }

   /* =====================================================
    * CONTENIDO DEL TAB
    * ===================================================== */
   private static function showTab(\User $user): void {

      $id     = $user->getID();
      $data   = self::getCounts($id);
      $config = \Config::getConfigurationValues('plugin_responsivas');

      global $DB;
      $email_row  = $DB->request([
         'FROM'  => 'glpi_useremails',
         'WHERE' => ['users_id' => $id, 'is_default' => 1],
      ])->current();
      $user_email       = trim($email_row['email'] ?? '');
      $email_configured = !empty(trim($config['email_subject'] ?? ''))
                       && !empty(trim($config['email_body']    ?? ''));

      $core_cfg = \Config::getConfigurationValues('core');
      $mail_ok  = ($core_cfg['use_notifications']    ?? 0) == 1
               && ($core_cfg['notifications_mailing'] ?? 0) == 1;

      if ($data['total'] <= 0) {
         $email_tooltip  = __('No responsibility documents to send', 'responsivas');
         $email_disabled = true;
      } elseif (empty($user_email)) {
         $email_tooltip  = __('No email address', 'responsivas');
         $email_disabled = true;
      } elseif (!$email_configured) {
         $email_tooltip  = __('Email not configured', 'responsivas');
         $email_disabled = true;
      } elseif (!$mail_ok) {
         $email_tooltip  = __('GLPI mail server not configured', 'responsivas');
         $email_disabled = true;
      } else {
         $email_tooltip  = __('Send selected responsibility documents to the user email', 'responsivas');
         $email_disabled = false;
      }

      $tz = $config['timezone'] ?? date_default_timezone_get();
      $dt = new \DateTime('now', new \DateTimeZone($tz));

      echo Twig::env()->render('user/tab.html.twig', [
         'user_id'        => $id,
         'counts'         => $data,
         'computer_url'   => Paths::routeUrl('computer?users_id=' . $id),
         'printer_url'    => Paths::routeUrl('printer?users_id=' . $id),
         'phone_url'      => Paths::routeUrl('phone?users_id=' . $id),
         'mail_url'       => Paths::routeUrl('mail'),
         'inspection_url' => Paths::routeUrl('inspection?users_id=' . $id),
         'return_url'     => Paths::routeUrl('return?users_id=' . $id),
         'config'         => $config,
         'user_email'     => $user_email,
         'email_disabled' => $email_disabled,
         'email_tooltip'  => $email_tooltip,
         'csrf_token'     => \Session::getNewCSRFToken(),
         'last_updated'   => $dt->format('d/m/Y H:i'),
      ]);
   }
}