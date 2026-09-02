<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas;

final class Utils
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function dateToText($fecha, ?string $timezone = null): string
    {
        if (empty($fecha) || $fecha === '0000-00-00') {
            return __('N/A', 'responsivas');
        }

        try {
            global $CFG_GLPI;
            $tzname = $timezone ?: ($_SESSION['glpi_tz'] ?? date_default_timezone_get());
            $tz = new \DateTimeZone($tzname);
            $dt = is_numeric($fecha)
                ? (new \DateTime('@' . $fecha))->setTimezone($tz)
                : new \DateTime($fecha, $tz);

            $locale = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? 'en_GB');
            if (!class_exists('IntlDateFormatter')) {
                return __('N/A', 'responsivas');
            }

            $fmt = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                $tz->getName(),
                \IntlDateFormatter::GREGORIAN
            );
            return (string)$fmt->format($dt);
        } catch (\Throwable) {
            return __('N/A', 'responsivas');
        }
    }

    public static function dropdownName(?int $id, string $table, string $default = 'N/A'): string
    {
        static $cache = [];
        if (!$id) {
            return $default;
        }
        if (!isset($cache[$table][$id])) {
            $cache[$table][$id] = \Dropdown::getDropdownName($table, $id);
        }
        return $cache[$table][$id];
    }

    public static function userName(int $uid): ?string
    {
        if ($uid <= 0) {
            return null;
        }
        $name = \User::getFriendlyNameById($uid);
        return $name !== '' ? self::escape($name) : null;
    }

    public static function renderTemplate(string $escapedText): string
    {
        $escapedText = str_replace('\\n', "\n", $escapedText);
        $lines = explode("\n", str_replace("\r\n", "\n", $escapedText));
        $html = '';
        $inList = false;
        $paragraph = [];

        $flush = static function () use (&$html, &$paragraph, &$inList): void {
            if ($inList) {
                $html .= '</ol>';
                $inList = false;
            }
            if ($paragraph !== []) {
                $html .= '<table nobr="true" width="100%"><tr><td style="text-align:justify;line-height:1.2;padding-bottom:3pt;">'
                    . implode('<br>', $paragraph)
                    . '</td></tr></table>';
                $paragraph = [];
            }
        };

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                if ($paragraph !== []) {
                    $flush();
                }
                if (!$inList) {
                    $html .= '<ol style="padding-left:18px;text-align:justify;line-height:1.2;">';
                    $inList = true;
                }
                $html .= '<li>' . $m[1] . '</li>';
                continue;
            }
            if ($line === '') {
                $flush();
                continue;
            }
            if ($inList) {
                $flush();
            }
            $paragraph[] = $line;
        }

        $flush();
        return $html;
    }

    public static function applyTemplate(string $template, array $vars): string
    {
        $template = str_replace('\\n', "\n", $template);
        $template = preg_replace('/<strong>(.*?)<\/strong>/is', '**$1**', $template);
        $template = preg_replace('/<em>(.*?)<\/em>/is', '*$1*', $template);
        $template = preg_replace('/<u>(.*?)<\/u>/is', '__$1__', $template);
        $template = preg_replace('/<span\s+style=["\']font-weight\s*:\s*bold["\']>(.*?)<\/span>/is', '**$1**', $template);
        $template = preg_replace('/<span\s+style=["\']font-style\s*:\s*italic["\']>(.*?)<\/span>/is', '*$1*', $template);
        $template = preg_replace('/<span\s+style=["\']text-decoration\s*:\s*underline["\']>(.*?)<\/span>/is', '__$1__', $template);
        $template = strip_tags($template);
        $template = self::escape($template);

        $template = preg_replace_callback('/\*\*(.+?)\*\*/s', static fn($m) => '<span style="font-weight:bold">' . $m[1] . '</span>', $template);
        $template = preg_replace_callback('/\*(.+?)\*/s', static fn($m) => '<span style="font-style:italic">' . $m[1] . '</span>', $template);
        $template = preg_replace_callback('/__(.+?)__/s', static fn($m) => '<span style="text-decoration:underline">' . $m[1] . '</span>', $template);
        return strtr($template, $vars);
    }

    public static function templateEditor(string $label, string $name, string $value, string $hint, int $rows = 5): void
    {
        $value = str_replace('\\n', "\n", $value);
        $value = preg_replace('/<strong>(.*?)<\/strong>/i', '**$1**', $value);
        $value = preg_replace('/<em>(.*?)<\/em>/i', '*$1*', $value);
        $value = preg_replace('/<u>(.*?)<\/u>/i', '__$1__', $value);
        $value = preg_replace('/<span style=["\']font-weight:bold["\']>(.*?)<\/span>/i', '**$1**', $value);
        $value = preg_replace('/<span style=["\']font-style:italic["\']>(.*?)<\/span>/i', '*$1*', $value);
        $value = preg_replace('/<span style=["\']text-decoration:underline["\']>(.*?)<\/span>/i', '__$1__', $value);
        $value = strip_tags($value);

        echo Twig::env()->render('partials/template_editor.html.twig', [
            'label' => $label,
            'name' => $name,
            'value' => $value,
            'hint' => $hint,
            'rows' => $rows,
        ]);
    }

    public static function variableHints(array $vars): void
    {
        echo Twig::env()->render('partials/variable_hints.html.twig', ['vars' => $vars]);
    }

}
