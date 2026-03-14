<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/* ============================
 * Flash error + redirect
 * ============================ */
function responsivasErrorAndBack(string $message): void {
   global $CFG_GLPI;
   Session::addMessageAfterRedirect($message, false, ERROR);
   Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
   exit;
}

/* ============================
 * Escape HTML seguro
 * ============================ */
function e(string $value): string {
   return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* ============================
 * Fecha a texto (ICU + TZ)
 * ============================ */
function fechaATexto($fecha, ?string $timezone = null): string {

   if (empty($fecha) || $fecha === '0000-00-00') {
      return __('N/D', 'responsivas');
   }

   try {
      global $CFG_GLPI;

      $tzname = $timezone
         ?: ($_SESSION['glpi_tz'] ?? date_default_timezone_get());

      $tz = new DateTimeZone($tzname);

      $dt = is_numeric($fecha)
         ? (new DateTime('@' . $fecha))->setTimezone($tz)
         : new DateTime($fecha, $tz);

      $locale = $_SESSION['glpilanguage'] ?? $CFG_GLPI['language'];

      if (!class_exists('IntlDateFormatter')) {
         return __('N/D', 'responsivas');
      }

      $fmt = new IntlDateFormatter(
         $locale,
         IntlDateFormatter::LONG,
         IntlDateFormatter::NONE,
         $tz->getName(),
         IntlDateFormatter::GREGORIAN
      );

      return $fmt->format($dt);

   } catch (Throwable) {
      return __('N/D', 'responsivas');
   }
}

/* ============================
 * Dropdown name con cache
 * ============================ */
function ddn(?int $id, string $table, string $default = 'N/D'): string {
   static $cache = [];

   if (!$id) {
      return $default;
   }

   if (!isset($cache[$table][$id])) {
      $cache[$table][$id] = Dropdown::getDropdownName($table, $id);
   }

   return $cache[$table][$id];
}

/* ============================
 * Nombre amigable usuario
 * ============================ */
function nombreUsuario(int $uid): ?string {
   if ($uid <= 0) {
      return null;
   }

   $nombre = User::getFriendlyNameById($uid);
   return $nombre !== '' ? e($nombre) : null;
}
/* ============================
 * Renderiza un texto de plantilla como HTML para TCPDF.
 *
 * Convenciones:
 *   - Líneas que empiezan con "N. " (dígito + punto + espacio) → <li> en <ol>
 *   - Línea en blanco → separador de párrafo
 *   - Resto → párrafo continuo (texto ya HTML-safe)
 *
 * El texto de entrada debe estar ya HTML-escapado (use e() en variables
 * antes de pasarlas a strtr, luego htmlspecialchars en el texto base).
 * ============================ */
function responsivasRenderTemplate(string $escaped_text): string
{
   // Normalizar \n literal a salto de línea real
   $escaped_text = str_replace('\\n', "\n", $escaped_text);
   $lines    = explode("\n", str_replace("\r\n", "\n", $escaped_text));
   $html     = '';
   $in_list  = false;
   $p_lines  = [];

   $flush_p = function () use (&$html, &$p_lines, &$in_list) {
      if ($in_list) {
         $html    .= '</ol>';
         $in_list  = false;
      }
      if (!empty($p_lines)) {
         $html    .= '<p style="text-align:justify;line-height:1.2;">' . implode('<br>', $p_lines) . '</p>';
         $p_lines  = [];
      }
   };

   foreach ($lines as $line) {
      $line = rtrim($line);
      // Orden: ** primero para que * no capture dentro de **texto**
      $line = preg_replace_callback('/\*\*(.+?)\*\*/s', static fn($m) => '<strong>' . $m[1] . '</strong>', $line);
      $line = preg_replace_callback('/\*(.+?)\*/s',       static fn($m) => '<em>'     . $m[1] . '</em>',     $line);
      $line = preg_replace_callback('/__(.+?)__/s',        static fn($m) => '<u>'      . $m[1] . '</u>',      $line);

      // Línea de lista: empieza con uno o más dígitos + punto + espacio
      if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
         // Vaciar párrafo acumulado antes de abrir lista
         if (!empty($p_lines)) {
            $flush_p();
         }
         if (!$in_list) {
            $html    .= '<ol style="padding-left:18px;text-align:justify;line-height:1.2;">';
            $in_list  = true;
         }
         $html .= '<li>' . $m[1] . '</li>';
         continue;
      }

      // Línea vacía → cierra lo que haya abierto
      if ($line === '') {
         $flush_p();
         continue;
      }

      // Línea normal
      if ($in_list) {
         $flush_p(); // cierra lista antes de continuar con párrafo
      }
      $p_lines[] = $line;
   }

   $flush_p(); // vaciar lo que quede

   return $html;
}

/* ============================
 * Sustituye variables en una plantilla de texto.
 * Escapa el texto base y reemplaza con valores ya HTML-safe.
 * ============================ */
function responsivasApplyTemplate(string $template, array $vars): string
{
   // Normalizar \n literal
   $template = str_replace('\\n', "\n", $template);
   // **texto** → <strong>texto</strong>  (antes de sustituir variables)
   // Orden: ** primero para que * no capture dentro de **texto**
   // Inline styles: mejor compatibilidad con clientes de correo (Outlook, etc.)
   $template = preg_replace_callback('/\*\*(.+?)\*\*/s', static fn($m) => '<span style="font-weight:bold">'          . $m[1] . '</span>', $template);
   $template = preg_replace_callback('/\*(.+?)\*/s',       static fn($m) => '<span style="font-style:italic">'         . $m[1] . '</span>', $template);
   $template = preg_replace_callback('/__(.+?)__/s',        static fn($m) => '<span style="text-decoration:underline">' . $m[1] . '</span>', $template);
   return strtr($template, $vars);
}

/* ============================
 * Editor visual de plantilla en config
 * ============================ */
function responsivasTemplateEditor(string $label, string $name, string $value, string $hint, int $rows = 5): void
{
   // Normalizar \n literal → salto real, y <strong> → **text** para visualización en textarea
   $value = str_replace('\\n', "\n", $value);
   $value = preg_replace('/<strong>(.*?)<\/strong>/i', '**$1**', $value);
   $value = preg_replace('/<em>(.*?)<\/em>/i', '*$1*', $value);
   $value = preg_replace('/<u>(.*?)<\/u>/i', '__$1__', $value);
   $value = preg_replace('/<span style=["\']font-weight:bold["\']>(.*?)<\/span>/i', '**$1**', $value);
   $value = preg_replace('/<span style=["\']font-style:italic["\']>(.*?)<\/span>/i', '*$1*', $value);
   $value = preg_replace('/<span style=["\']text-decoration:underline["\']>(.*?)<\/span>/i', '__$1__', $value);
   // Limpiar cualquier otra etiqueta HTML que haya quedado
   $value = strip_tags($value);
   $value_esc = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   $hint_esc  = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');
   echo "
   <div class='mb-3'>
      <label class='form-label fw-bold'>
         <i class='ti ti-file-pencil me-1'></i>{$label}
      </label>";
   echo "<div class='d-flex gap-1 mb-1'>"
      . "<button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='**' title='Negrita — **texto**'><b>B</b>&nbsp;<small class='fw-normal opacity-75'>**</small></button>"
      . "<button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='*' title='Cursiva — *texto*'><i>I</i>&nbsp;<small class='fw-normal opacity-75'>*</small></button>"
      . "<button type='button' class='btn btn-sm btn-outline-secondary resp-fmt-btn' data-wrap='__' title='Subrayado — __texto__'><u>U</u>&nbsp;<small class='fw-normal opacity-75'>__</small></button>"
      . "</div>";
   echo "<textarea class='form-control font-monospace'
                name='{$name}'
                rows='{$rows}'
                spellcheck='false'
                style='font-size:0.82rem;resize:vertical;'>{$value_esc}</textarea>
      <div class='form-text'>{$hint_esc}</div>
   </div>";
}

/* ============================
 * Lista de etiquetas disponibles en config
 * ============================ */
function responsivasVariableHints(array $vars): void
{
   echo '<div class="alert alert-info d-flex align-items-start mb-3" role="alert">';
   echo '<i class="ti ti-tags me-2 fs-5 mt-1"></i>';
   echo '<div style="font-size:0.85rem;">';
   echo '<strong>' . __('Etiquetas disponibles', 'responsivas') . ':</strong> '
      . '<span class="text-muted">' . '<b>**' . __('negrita', 'responsivas') . '**</b>' . ' &nbsp;&bull;&nbsp; <i>*' . __('cursiva', 'responsivas') . '*</i>' . ' &nbsp;&bull;&nbsp; <u>__' . __('subrayado', 'responsivas') . '__</u>' . '</span><br>';
   echo '<span class="text-muted d-block mt-1" style="font-size:0.82em;"><i class="ti ti-hand-click me-1"></i>' . __('Haz clic en una variable para insertarla en el campo activo.', 'responsivas') . '</span>';
   $first = true;
   foreach ($vars as $tag => $desc) {
      $tag_safe  = htmlspecialchars($tag,  ENT_QUOTES, 'UTF-8');
      $desc_safe = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
      if (!$first) {
         echo '<br>';
      }
      // data-resp-var evita todo escapado de comillas en el onclick
      echo '<code'
         . ' class="me-1"'
         . ' style="cursor:pointer;"'
         . ' title="' . $desc_safe . '"'
         . ' data-resp-var="' . $tag_safe . '"'
         . ' onclick="responsivasInsertVar(this.dataset.respVar)">'
         . $tag_safe
         . '</code> &mdash; ' . $desc_safe;
      $first = false;
   }
   echo '</div></div>';
}
