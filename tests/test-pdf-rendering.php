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
                    'pho_font_size' => 8.2,
                    'show_qr' => 1,
                    'pc_footer_left_1' => 'CLI PDF smoke test',
                    'pc_footer_right_1' => '',
                    'pc_footer_left_2' => '',
                    'pc_footer_right_2' => '',
                    'pho_footer_left_1' => 'Original: Company',
                    'pho_footer_right_1' => 'Copy: Employee',
                    'pho_footer_left_2' => 'SIS-RESP-002',
                    'pho_footer_right_2' => 'Rev 1.6.7',
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
require_once $pluginRoot . '/src/Utils.php';
require_once $pluginRoot . '/src/Pdf/PdfBuilder.php';

$GLOBALS['responsivas_pdf_auto_page_break_history'] = [];
$GLOBALS['responsivas_pdf_footer_restoration'] = [];

$pdf = new class('P', 'mm', 'LETTER') extends \GlpiPlugin\Responsivas\Pdf\PDF {
    public array $qrUrlsAtFooter = [];

    public function setAutoPageBreak($auto, $margin = 0)
    {
        $GLOBALS['responsivas_pdf_auto_page_break_history'][] = [(bool)$auto, $margin];
        parent::setAutoPageBreak($auto, $margin);
    }

    public function Footer(): void
    {
        $page = $this->getPage();
        $this->qrUrlsAtFooter[$page] = $this->getQrUrlForPage($page);

        $initialAutoPageBreak = $this->getAutoPageBreak();
        $initialBreakMargin = $this->getBreakMargin();
        $historyStart = count($GLOBALS['responsivas_pdf_auto_page_break_history']);

        parent::Footer();

        $transitions = array_slice(
            $GLOBALS['responsivas_pdf_auto_page_break_history'],
            $historyStart
        );
        $restored = $transitions === []
            ? null
            : $transitions[count($transitions) - 1];

        $GLOBALS['responsivas_pdf_footer_restoration'][] = [
            'initial_auto' => $initialAutoPageBreak,
            'initial_margin' => $initialBreakMargin,
            'transitions' => $transitions,
            'restored_auto' => $restored[0] ?? null,
            'restored_margin' => $restored[1] ?? null,
        ];
    }
};
$pdf->setDocumentType('pho_font_size', 'pho');
$pdf->fecha_header = '2026-11-11';
$pdf->location = 'Hermosillo, Sonora';
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(true, 25.0);
$pdf->SetPrintHeader(true);
$pdf->SetPrintFooter(true);
$pdf->setQrForAllPages('https://glpi.example/front/phone.form.php?id=42');
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 8.2);
$pdf->setQrForPage($pdf->getPage(), 'https://glpi.example/front/phone.form.php?id=42');

// Exercise the real GLPI TCPDF engine. With a sufficiently high cursor there is
// room for the complete signature grid, so it must stay on the current page.
$pdf->SetY(180);
$signatureRenderer = new ReflectionMethod(
    \GlpiPlugin\Responsivas\Pdf\PdfBuilder::class,
    'renderPhoneSignatureBlock'
);
$signatureRenderer->setAccessible(true);
$signatureArguments = [
    $pdf,
    'Legal Representative',
    'Assigned Borrower',
    'Employee No.: 0934',
    'Witness One',
    'Witness Two',
    'https://glpi.example/front/phone.form.php?id=42',
];
$signatureRenderer->invokeArgs(null, $signatureArguments);
if ($pdf->getPage() !== 1) {
    throw new RuntimeException('Phone signature block moved to a new page despite sufficient printable space.');
}

// Near the footer-safe boundary, the same block must move together to a new page.
$pdf->SetY(225);
$signatureRenderer->invokeArgs(null, $signatureArguments);
if ($pdf->getPage() !== 2) {
    throw new RuntimeException('Phone signature block did not move together when the remaining printable area was insufficient.');
}

// Exercise long flowing HTML on three or more pages. TCPDF 6.x invokes the
// outgoing page footer while writeHTML() is creating the next page, before
// callers can register per-page URLs after rendering has finished.
$continuationQrUrl = 'https://glpi.example/front/phone.form.php?id=84';
$pdf->setQrForAllPages($continuationQrUrl);
$pdf->AddPage();
$flowStartPage = $pdf->getPage();
$pdf->SetFont('helvetica', '', 8.2);
$pdf->SetY(30);
$longPhoneProse = str_repeat(
    '<p style="line-height:1.2;">Phone contract QR continuation regression test. This editable clause intentionally flows across multiple pages so each automatic page break exercises the footer QR lookup before the final page map is available.</p>',
    140
);
$pdf->writeHTML($longPhoneProse, true, false, true, false, '');
$lastFlowPage = $pdf->getPage();
if (($lastFlowPage - $flowStartPage) < 2) {
    throw new RuntimeException('The long phone-contract fixture did not generate at least three flowing pages.');
}

// Keep explicit mappings as the real builder does. The assertions below use
// the URL captured when each footer ran, not only this final per-page map.
for ($flowPage = $flowStartPage; $flowPage <= $lastFlowPage; $flowPage++) {
    $pdf->setQrForPage($flowPage, $continuationQrUrl);
}

// TCPDF 6 clears its current-page state while Output() closes the document.
// Capture the expected count while the document is still open; do not call
// getPage() after Output(), when the internal page property is no longer set.
$expectedPageCount = $pdf->getPage();
$content = $pdf->Output('responsivas-smoke.pdf', 'S');

if (!is_string($content) || !str_starts_with($content, '%PDF-') || strlen($content) < 500) {
    throw new RuntimeException('TCPDF did not return a valid non-empty PDF byte string.');
}
$pageObjectCount = preg_match_all('#/Type\\s*/Page\\b#', $content);
if ($pageObjectCount < 4 || $pageObjectCount !== $expectedPageCount) {
    throw new RuntimeException(
        sprintf(
            'Expected %d PDF page objects (at least four) after the signature and flowing-clause scenarios, found %s.',
            $expectedPageCount,
            var_export($pageObjectCount, true)
        )
    );
}

$qrUrlsAtFooter = $pdf->qrUrlsAtFooter;
for ($pageNumber = 1; $pageNumber <= $pageObjectCount; $pageNumber++) {
    $expectedQrUrl = $pageNumber >= $flowStartPage
        ? $continuationQrUrl
        : 'https://glpi.example/front/phone.form.php?id=42';
    if (($qrUrlsAtFooter[$pageNumber] ?? null) !== $expectedQrUrl) {
        throw new RuntimeException(
            sprintf(
                'Page %d footer did not receive its QR URL before rendering (expected %s, received %s).',
                $pageNumber,
                $expectedQrUrl,
                var_export($qrUrlsAtFooter[$pageNumber] ?? null, true)
            )
        );
    }
}
$footerRestorations = $GLOBALS['responsivas_pdf_footer_restoration'];
if ($footerRestorations === []) {
    throw new RuntimeException('TCPDF did not invoke the Responsivas PDF footer.');
}
foreach ($footerRestorations as $restoration) {
    $transitions = $restoration['transitions'];
    if (
        count($transitions) < 2
        || !is_bool($restoration['initial_auto'])
        || $transitions[0][0] !== false
        || $restoration['restored_auto'] !== $restoration['initial_auto']
        || $restoration['restored_margin'] !== $restoration['initial_margin']
    ) {
        throw new RuntimeException(
            'Responsivas PDF footer did not restore the incoming TCPDF page-break state and margin: '
            . json_encode($restoration, JSON_UNESCAPED_SLASHES)
        );
    }
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
        "TCPDF phone-signature integration test passed: %d bytes, %d page(s), TCPDF class %s.\n",
        strlen($content),
        $pageObjectCount,
        get_parent_class(\GlpiPlugin\Responsivas\Pdf\PDF::class) ?: 'unknown'
    )
);
