<?php

class PluginResponsivasUser extends CommonGLPI {

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

      $config            = Config::getConfigurationValues('plugin_responsivas');
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
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if (!$item instanceof User) {
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
      CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ) {
      if ($item instanceof User) {
         self::showTab($item);
      }
      return true;
   }

   /* =====================================================
    * BOTÓN reutilizable (generación de PDF)
    * ===================================================== */
   private static function button(
      int $count,
      string $url,
      string $icon,
      string $label,
      string $class,
      string $tooltip_empty = ''
   ): string {

      $tooltip = $count
         ? __('Generate responsibility documents', 'responsivas')
         : ($tooltip_empty ?: __('No equipment assigned', 'responsivas'));

      $disabled   = $count ? '' : 'disabled';
      $href       = $count ? htmlspecialchars($url, ENT_QUOTES) : '#';
      $extra_attr = $count
         ? "target='_blank' data-resp-pdf-btn='1'"
         : "aria-disabled='true' role='button' style='pointer-events:none'";

      return "
      <span data-bs-toggle='tooltip' title='{$tooltip}' class='d-inline-block'>
         <a class='{$class} {$disabled}'
            href='{$href}'
            {$extra_attr}>
            <i class='ti {$icon} me-2'></i>
            {$label}
            <span class='badge badge-secondary ms-1'>{$count}</span>
         </a>
      </span>";
   }

   /* =====================================================
    * SECCIÓN de envío por correo (con selección de tipos)
    * ===================================================== */
   private static function emailButton(
      int $user_id,
      array $counts,
      string $user_email,
      bool $email_configured,
      bool $mail_ok,
      string $base
   ): string {

      $total = $counts['total'];

      if ($total <= 0) {
         $tooltip  = __('No responsibility documents to send', 'responsivas');
         $disabled = true;
      } elseif (empty($user_email)) {
         $tooltip  = __('No email address', 'responsivas');
         $disabled = true;
      } elseif (!$email_configured) {
         $tooltip  = __('Email not configured', 'responsivas');
         $disabled = true;
      } elseif (!$mail_ok) {
         $tooltip  = __('GLPI mail server not configured', 'responsivas');
         $disabled = true;
      } else {
         $tooltip  = __('Send selected responsibility documents to the user email', 'responsivas');
         $disabled = false;
      }

      $tooltip_escaped = htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8');

      if ($disabled) {
         return "
         <span data-bs-toggle='tooltip' title='{$tooltip_escaped}' class='d-inline-block'>
            <button type='button'
                    class='btn btn-success disabled'
                    aria-disabled='true'
                    style='pointer-events:none'>
               <i class='ti ti-mail me-2'></i>
               " . __('Send by email', 'responsivas') . "
            </button>
         </span>";
      }

      $form_id    = 'form_email_resp_' . $user_id;
      $modal_id   = 'modal_email_resp_' . $user_id;
      $csrf_token = htmlspecialchars(Session::getNewCSRFToken(), ENT_QUOTES);
      $user_email_safe = htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8');

      $lbl_send    = __('Send by email', 'responsivas');
      $lbl_confirm = __('Confirm sending', 'responsivas');
      $lbl_cancel  = __('Cancel', 'responsivas');
      $lbl_body    = sprintf(__('Send the responsibility documents to %s?', 'responsivas'), "<strong>{$user_email_safe}</strong>");
      $lbl_select  = __('Select document types to send:', 'responsivas');
      $lbl_comp    = __('Computers', 'responsivas');
      $lbl_print   = __('Printers', 'responsivas');
      $lbl_phone   = __('Phones', 'responsivas');
      $lbl_none    = __('Select at least one document type.', 'responsivas');

      $chk_comp  = $counts['computers'] > 0
         ? "<div class='form-check'>
               <input class='form-check-input resp-type-check' type='checkbox' name='send_computers' id='chk_comp_{$user_id}' value='1' checked>
               <label class='form-check-label' for='chk_comp_{$user_id}'>
                  <i class='ti ti-device-desktop me-1'></i>{$lbl_comp}
                  <span class='badge ms-1' style='background-color:var(--tblr-border-color,#dee2e6);color:var(--tblr-body-color,#1e293b);'>{$counts['computers']}</span>
               </label>
            </div>"
         : '';

      $chk_print = $counts['printers'] > 0
         ? "<div class='form-check'>
               <input class='form-check-input resp-type-check' type='checkbox' name='send_printers' id='chk_print_{$user_id}' value='1' checked>
               <label class='form-check-label' for='chk_print_{$user_id}'>
                  <i class='ti ti-printer me-1'></i>{$lbl_print}
                  <span class='badge ms-1' style='background-color:var(--tblr-border-color,#dee2e6);color:var(--tblr-body-color,#1e293b);'>{$counts['printers']}</span>
               </label>
            </div>"
         : '';

      $chk_phone = $counts['phones'] > 0
         ? "<div class='form-check'>
               <input class='form-check-input resp-type-check' type='checkbox' name='send_phones' id='chk_phone_{$user_id}' value='1' checked>
               <label class='form-check-label' for='chk_phone_{$user_id}'>
                  <i class='ti ti-device-mobile me-1'></i>{$lbl_phone}
                  <span class='badge ms-1' style='background-color:var(--tblr-border-color,#dee2e6);color:var(--tblr-body-color,#1e293b);'>{$counts['phones']}</span>
               </label>
            </div>"
         : '';

      return "
      <!-- Formulario oculto — los checkboxes del modal se copian aquí al confirmar -->
      <form id='{$form_id}' method='post' action='{$base}/send_mail.php' style='display:none;'>
         <input type='hidden' name='users_id' value='{$user_id}'>
         <input type='hidden' name='_glpi_csrf_token' value='{$csrf_token}'>
         <input type='hidden' name='send_computers' value='' id='hidden_comp_{$user_id}'>
         <input type='hidden' name='send_printers'  value='' id='hidden_print_{$user_id}'>
         <input type='hidden' name='send_phones'    value='' id='hidden_phone_{$user_id}'>
      </form>

      <!-- Botón que abre el modal -->
      <span data-bs-toggle='tooltip' title='{$tooltip_escaped}' class='d-inline-block'>
         <button type='button' class='btn btn-success' data-bs-toggle='modal' data-bs-target='#{$modal_id}'>
            <i class='ti ti-mail me-2'></i>{$lbl_send}
         </button>
      </span>

      <!-- Modal de confirmación con checkboxes -->
      <div class='modal fade' id='{$modal_id}' tabindex='-1' aria-labelledby='{$modal_id}_label' aria-hidden='true'>
         <div class='modal-dialog modal-dialog-centered'>
            <div class='modal-content'>
               <div class='modal-header'>
                  <h5 class='modal-title' id='{$modal_id}_label'>
                     <i class='ti ti-mail me-2'></i>{$lbl_confirm}
                  </h5>
                  <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
               </div>
               <div class='modal-body'>
                  <p class='mb-3'>{$lbl_body}</p>
                  <p class='fw-bold mb-2'>{$lbl_select}</p>
                  {$chk_comp}
                  {$chk_print}
                  {$chk_phone}
                  <div class='alert alert-warning mt-2 d-none' id='warn_none_{$user_id}'>
                     <i class='ti ti-alert-triangle me-1'></i>{$lbl_none}
                  </div>
               </div>
               <div class='modal-footer'>
                  <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                     <i class='ti ti-x me-1'></i>{$lbl_cancel}
                  </button>
                  <button type='button' class='btn btn-success' id='btn_send_{$form_id}'
                          onclick=\"(function(btn){
                             var modal = document.getElementById('{$modal_id}');
                             var checks = modal.querySelectorAll('.resp-type-check:checked');
                             if(checks.length === 0){
                                document.getElementById('warn_none_{$user_id}').classList.remove('d-none');
                                return;
                             }
                             btn.disabled=true;
                             var icon=btn.querySelector('i');
                             if(icon){icon.className='spinner-border spinner-border-sm me-1';icon.setAttribute('role','status');}
                             // Copy checkbox state to hidden form fields
                             var names=['send_computers','send_printers','send_phones'];
                             var ids=['hidden_comp_{$user_id}','hidden_print_{$user_id}','hidden_phone_{$user_id}'];
                             names.forEach(function(name,i){
                                var chk=modal.querySelector('[name='+name+']:checked');
                                document.getElementById(ids[i]).value=chk?'1':'';
                             });
                             document.getElementById('{$form_id}').submit();
                          })(this)\">
                     <i class='ti ti-send me-1'></i>{$lbl_send}
                  </button>
               </div>
            </div>
         </div>
      </div>";
   }

   /* =====================================================
    * CONTENIDO DEL TAB
    * ===================================================== */
   private static function showTab(User $user) {

      $id     = $user->getID();
      $data   = self::getCounts($id);
      $base   = Plugin::getWebDir('responsivas') . '/front';
      $config = Config::getConfigurationValues('plugin_responsivas');

      // Datos para el botón de correo
      // El email en GLPI está en glpi_useremails, no en glpi_users
      global $DB;
      $email_row  = $DB->request([
         'FROM'  => 'glpi_useremails',
         'WHERE' => ['users_id' => $id, 'is_default' => 1],
      ])->current();
      $user_email = trim($email_row['email'] ?? '');
      $email_configured = !empty(trim($config['email_subject'] ?? ''))
                       && !empty(trim($config['email_body']    ?? ''));

      // Verificar si GLPI tiene correo habilitado
      $core_cfg = Config::getConfigurationValues('core');
      $mail_ok  = ($core_cfg['use_notifications']    ?? 0) == 1
               && ($core_cfg['notifications_mailing'] ?? 0) == 1;

      echo "
      <div class='card mt-3 shadow-sm'>
         <div class='card-header mb-3 pt-2 position-relative'>
            <div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
               <i class='fs-2x ti ti-file-text'></i>
            </div>
            <h4 class='card-title ms-5 mb-0'>" .
               __('Responsibility Document Generator', 'responsivas') .
            "</h4>
         </div>

         <h3 class='text-center mt-3'>" .
            __('Select the asset type to generate responsibility documents', 'responsivas') .
         "</h3>";

        if (empty($config['cellphone_type_id'])) {
           echo "
           <div class='alert alert-warning mx-auto' style='max-width:600px'>
              <i class='ti ti-alert-triangle me-2'></i>
              " . __('The phone type for responsibility documents has not been configured. Contact the administrator.', 'responsivas') . "
           </div>";
        }

      echo "<div class='d-flex flex-wrap justify-content-center gap-3 mt-4 mb-2'>";

      echo self::button(
         $data['computers'],
         "{$base}/computer.php?users_id={$id}",
         'ti-device-desktop',
         __('Computers', 'responsivas'),
         'btn btn-primary',
         __('No computers assigned', 'responsivas')
      );

      echo self::button(
         $data['printers'],
         "{$base}/printer.php?users_id={$id}",
         'ti-printer',
         __('Printers', 'responsivas'),
         'btn btn-secondary',
         __('No printers assigned', 'responsivas')
      );

      echo self::button(
         $data['phones'],
         "{$base}/phone.php?users_id={$id}",
         'ti-device-mobile',
         __('Phones', 'responsivas'),
         'btn btn-info',
         __('No phones of the configured type assigned', 'responsivas')
      );

      echo "</div>";

      // ── Separador + botón de correo ──────────────────────
      echo "<div class='d-flex flex-wrap justify-content-center gap-3 mb-4 mt-2'>";
      echo self::emailButton($id, $data, $user_email, $email_configured, $mail_ok, $base);
      echo "</div>";

      $tz = $config['timezone'] ?? date_default_timezone_get();
      $dt = new DateTime('now', new DateTimeZone($tz));
      ?>
      <div class="card-footer text-end py-2">
         <small><?php echo __('Last updated: ', 'responsivas') . $dt->format('d/m/Y H:i'); ?></small>
      </div>
      </div><!-- /card -->
      <script>
         (function () {
            if (typeof bootstrap !== 'undefined') {
               document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                  if (!el.dataset.tooltipInit) {
                     new bootstrap.Tooltip(el);
                     el.dataset.tooltipInit = '1';
                  }
               });
            }
            document.querySelectorAll('[data-resp-pdf-btn="1"]').forEach(function (btn) {
               btn.addEventListener('click', function () {
                  var icon = btn.querySelector('i');
                  var origClass = icon ? icon.className : '';
                  if (icon) icon.className = 'spinner-border spinner-border-sm me-2';
                  btn.classList.add('disabled');
                  btn.setAttribute('aria-disabled', 'true');
                  setTimeout(function () {
                     btn.classList.remove('disabled');
                     btn.removeAttribute('aria-disabled');
                     if (icon) icon.className = origClass;
                  }, 5000);
               });
            });
         })();
      </script>
      <?php
   }
}