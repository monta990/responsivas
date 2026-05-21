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
   $referer  = $_SERVER['HTTP_REFERER'] ?? '';
   $base     = $CFG_GLPI['url_base'] ?? '';
   $fallback = $CFG_GLPI['root_doc'] ?? '/';
   $target   = ($base !== '' && str_starts_with($referer, $base)) ? $referer : $fallback;
   Html::redirect($target);
   exit;
}

/* ============================
 * Escape HTML seguro
 * ============================ */
function cleanerEscape(string $value): string {
   return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* ============================
 * Fecha a texto (ICU + TZ)
 * ============================ */
function fechaATexto($fecha, ?string $timezone = null): string {

   if (empty($fecha) || $fecha === '0000-00-00') {
      return __('N/A', 'responsivas');
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
         return __('N/A', 'responsivas');
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
      return __('N/A', 'responsivas');
   }
}

/* ============================
 * Dropdown name con cache
 * ============================ */
function ddn(?int $id, string $table, string $default = 'N/A'): string {
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
   return $nombre !== '' ? cleanerEscape($nombre) : null;
}
/* ============================
 * Renderiza un texto de plantilla como HTML para TCPDF.
 *
 * Convenciones:
 *   - Líneas que empiezan con "N. " (dígito + punto + espacio) → <li> en <ol>
 *   - Línea en blanco → separador de párrafo
 *   - Resto → párrafo continuo (texto ya HTML-safe)
 *
 * El texto de entrada debe estar ya HTML-escapado (use cleanerEscape() en variables
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
         $html    .= '<table nobr="true" width="100%"><tr><td style="text-align:justify;line-height:1.2;padding-bottom:3pt;">' . implode('<br>', $p_lines) . '</td></tr></table>';
         $p_lines  = [];
      }
   };

   foreach ($lines as $line) {
      $line = rtrim($line);

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
   $value = str_replace('\\n', "\n", $value);
   $value = preg_replace('/<strong>(.*?)<\/strong>/i', '**$1**', $value);
   $value = preg_replace('/<em>(.*?)<\/em>/i', '*$1*', $value);
   $value = preg_replace('/<u>(.*?)<\/u>/i', '__$1__', $value);
   $value = preg_replace('/<span style=["\']font-weight:bold["\']>(.*?)<\/span>/i', '**$1**', $value);
   $value = preg_replace('/<span style=["\']font-style:italic["\']>(.*?)<\/span>/i', '*$1*', $value);
   $value = preg_replace('/<span style=["\']text-decoration:underline["\']>(.*?)<\/span>/i', '__$1__', $value);
   $value = strip_tags($value);

   echo PluginResponsivasTwig::env()->render('partials/template_editor.html.twig', [
      'label' => $label,
      'name'  => $name,
      'value' => $value,
      'hint'  => $hint,
      'rows'  => $rows,
   ]);
}

/* ============================
 * Lista de etiquetas disponibles en config
 * ============================ */
function responsivasVariableHints(array $vars): void
{
   echo PluginResponsivasTwig::env()->render('partials/variable_hints.html.twig', [
      'vars' => $vars,
   ]);
}
