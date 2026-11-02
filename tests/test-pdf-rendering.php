<?php
declare(strict_types=1);

/**
 * Real TCPDF smoke test against the TCPDF version bundled by each supported
 * GLPI branch (GLPI 11 and GLPI 12). No database or production files are used.
 *
 * Usage:
 *   php tests/test-pdf-rendering.php /path/to/glpi /path/to/glpi/plugins/responsivas
 */
$glpiRoot = realpath($argv[1] ?? '');
$pluginRoot = realpath($argv[2] ?? '');

if ($glpiRoot === false || !is_file($glpiRoot . '/vendor/autoload.php')) {
    fwrite(STDERR, "A GLPI checkout with vendor/autoload.php is required.\n");
    exit(2);
}
if ($pluginRoot === false || !is_file($pluginRoot . '/src/Pdf/PDF.php')) {
    fwrite(STDERR, "The Responsivas plugin source directory is required.\n");
    exit(2);
}

$pluginDocs = sys_get_temp_dir() . '/responsivas-pdf-smoke-docs';
if (!is_dir($pluginDocs . '/responsivas')) {
    mkdir($pluginDocs . '/responsivas', 0750, true);
}

$GLOBALS['CFG_GLPI'] = ['root_doc' => '/glpi'];
$_SESSION = [];


if (!class_exists('Config', false)) {
    final class Config
    {
        public static function getConfigurationValues(string $context): array
        {
            if ($context === 'plugin_responsivas') {
                return [
                    'pc_font_size' => 10,
                    'show_qr' => 0,
                    'pc_footer_left_1' => 'CLI PDF smoke test',
                    'pc_footer_right_1' => '',
                    'pc_footer_left_2' => '',
                    'pc_footer_right_2' => '',
                ];
            }

            return [];
        }

        public static function getConfigurationValue(string $context, string $key): mixed
        {
            return $context === 'core' && $key === 'pdffont' ? 'helvetica' : null;
        }
    }
}

require_once $glpiRoot . '/vendor/autoload.php';
require_once $glpiRoot . '/src/autoload/constants.php';

if (!defined('GLPI_ROOT') || realpath(GLPI_ROOT) !== $glpiRoot) {
    throw new RuntimeException('GLPI core constants did not resolve to the selected GLPI checkout.');
}

// Define only plugin-specific paths used by Responsivas and not populated by
// the core bootstrap in this isolated smoke-test context.
if (!defined('GLPI_PLUGINS_DIRECTORIES')) {
    define('GLPI_PLUGINS_DIRECTORIES', [dirname($pluginRoot)]);
}
if (!defined('GLPI_PLUGIN_DOC_DIR')) {
    define('GLPI_PLUGIN_DOC_DIR', $pluginDocs);
}

if (!class_exists('TCPDF')) {
    $legacyTcpdf = $glpiRoot . '/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (!is_file($legacyTcpdf)) {
        fwrite(STDERR, "The GLPI TCPDF class could not be loaded.\n");
        exit(2);
    }
    require_once $legacyTcpdf;
}


require_once $pluginRoot . '/src/Paths.php';
require_once $pluginRoot . '/src/Pdf/PDF.php';

$pdf = new \GlpiPlugin\Responsivas\Pdf\PDF('P', 'mm', 'LETTER');
$pdf->setDocumentType('pc_font_size', 'pc');
$pdf->fecha_header = '2026-11-02';
$pdf->location = 'Hermosillo, Sonora';
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);
$pdf->Write(6, 'Responsivas TCPDF integration smoke test');

$autoPageBreakBeforeOutput = $pdf->getAutoPageBreak();
$content = $pdf->Output('responsivas-smoke.pdf', 'S');

if (!is_string($content) || !str_starts_with($content, '%PDF-') || strlen($content) < 500) {
    throw new RuntimeException('TCPDF did not return a valid non-empty PDF byte string.');
}
if ($pdf->getNumPages() !== 1) {
    throw new RuntimeException('The one-page smoke document unexpectedly changed its page count.');
}
if ($pdf->getAutoPageBreak() !== $autoPageBreakBeforeOutput) {
    throw new RuntimeException('Responsivas PDF footer did not restore TCPDF page-break state.');
}

$parentClass = get_parent_class(\GlpiPlugin\Responsivas\Pdf\PDF::class);
if ($parentClass !== false && method_exists($parentClass, 'fileAllowedPaths')) {
    $reflection = new ReflectionMethod(\GlpiPlugin\Responsivas\Pdf\PDF::class, 'fileAllowedPaths');
    $reflection->setAccessible(true);
    $allowedPaths = $reflection->invoke($pdf);

    $pluginRealPath = realpath($pluginRoot);
    $filesRealPath = realpath(\GlpiPlugin\Responsivas\Paths::filesDir());

    if ($pluginRealPath === false || !in_array($pluginRealPath, $allowedPaths, true)) {
        throw new RuntimeException('TCPDF 7 local-file allowlist is missing the plugin schematics directory.');
    }
    if ($filesRealPath !== false && !in_array($filesRealPath, $allowedPaths, true)) {
        throw new RuntimeException('TCPDF 7 local-file allowlist is missing the GLPI plugin documents directory.');
    }
}

fwrite(
    STDOUT,
    sprintf(
        "TCPDF PDF smoke test passed: %d bytes, %d page(s), TCPDF class %s.\n",
        strlen($content),
        $pdf->getNumPages(),
        get_parent_class(\GlpiPlugin\Responsivas\Pdf\PDF::class) ?: 'unknown'
    )
);
