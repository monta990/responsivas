<?php

if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

/**
 * Clase base para generación de PDFs de responsivas.
 *
 * Centraliza Header, Footer y QR para evitar duplicación en
 * computer.php, printer.php y phone.php.
 *
 * Uso:
 *   $pdf = new PluginResponsivasPDF('P', 'mm', 'LETTER');
 *   $pdf->setDocumentType('pc_font_size', 'pc');
 */
class PluginResponsivasPDF extends TCPDF {

    public string $fecha_header  = '';
    public string $location      = '';
    public bool   $show_watermark = false;
    public string $watermark_text = 'VISTA PREVIA';

    /** Set before creating a PDF instance to enable watermark on all pages */
    public static $global_watermark      = false;
    public static $global_watermark_text = 'VISTA PREVIA';

    protected array  $qr_per_page   = [];
    protected string $font_size_key = 'pc_font_size';
    protected string $footer_prefix = 'pc';

    /**
     * Configura el tipo de documento: qué clave de fuente y qué prefijo
     * de footer usar ('pc', 'pri' o 'pho').
     */
    public function setDocumentType(string $font_size_key, string $footer_prefix): void {
        $this->font_size_key = $font_size_key;
        $this->footer_prefix = $footer_prefix;
    }

    public function setQrForPage(int $page, string $url): void {
        $this->qr_per_page[$page] = $url;
    }


    /**
     * Dibuja la marca de agua diagonal centrada en la página actual.
     * Se llama desde Header() para que aparezca en cada página.
     */
    protected function drawWatermark(): void
    {
        // Support both instance flag and static class flag
        if (!$this->show_watermark && !static::$global_watermark) {
            return;
        }
        $text = $this->show_watermark ? $this->watermark_text : static::$global_watermark_text;
        $this->StartTransform();
        $this->SetFont('helvetica', 'B', 52);
        $this->SetTextColor(200, 200, 200);
        $this->SetAlpha(0.25);

        $x = $this->getPageWidth()  / 2;
        $y = $this->getPageHeight() / 2;

        $this->Rotate(45, $x, $y);
        $this->Text($x - 55, $y, $text);
        $this->Rotate(0);

        $this->SetAlpha(1);
        $this->SetTextColor(0, 0, 0);
        $this->StopTransform();
        $this->SetFont(
            Config::getConfigurationValue('core', 'pdffont'),
            '',
            (int)(Config::getConfigurationValues('plugin_responsivas')[$this->font_size_key] ?? 10)
        );
    }

        public function Header(): void {
        $img_file = PluginResponsivasPaths::logoPath();
        if (is_readable($img_file)) {
            $this->Image($img_file, 20, 10, 80, 0, '', '', '', true, 300);
        }

        $cfg = Config::getConfigurationValues('plugin_responsivas');
        $this->SetFont(
            Config::getConfigurationValue('core', 'pdffont'),
            '',
            (int)($cfg[$this->font_size_key] ?? 10)
        );
        $this->SetY(15);
        $this->Cell(0, 5, $this->location . ' a ' . $this->fecha_header, 0, 1, 'R');
        $this->drawWatermark();
    }

    /**
     * Convierte marcadores **bold**, *italic*, __underline__ a HTML inline.
     * Mismo orden que responsivasApplyTemplate para resultados consistentes.
     */
    private static function fmtCell(string $text): string {
        $text = preg_replace_callback('/\*\*(.+?)\*\*/s', static fn($m) => '<b>'  . $m[1] . '</b>', $text);
        $text = preg_replace_callback('/\*(.+?)\*/s',       static fn($m) => '<i>'  . $m[1] . '</i>', $text);
        $text = preg_replace_callback('/__(.+?)__/s',         static fn($m) => '<u>'  . $m[1] . '</u>', $text);
        return $text;
    }

    public function Footer(): void {
        $this->SetY(-20);

        $cfg = Config::getConfigurationValues('plugin_responsivas');
        $p   = $this->footer_prefix;
        $fs  = (int)($cfg[$this->font_size_key] ?? 10);

        $this->SetFont(Config::getConfigurationValue('core', 'pdffont'), '', $fs);

        $pageW = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $half  = $pageW / 2;
        $h     = 5;
        $x     = $this->getMargins()['left'];

        // Fila 1: capturar Y antes de escribir para que ambas celdas queden en la misma línea
        $y1 = $this->GetY();
        $this->writeHTMLCell($half, $h, $x,           $y1, self::fmtCell($cfg["{$p}_footer_left_1"]  ?? ''), 0, 0, false, true, 'L');
        $this->writeHTMLCell($half, $h, $x + $half,   $y1, self::fmtCell($cfg["{$p}_footer_right_1"] ?? ''), 0, 1, false, true, 'R');

        // Fila 2: inferior izquierda | inferior derecha
        $y2 = $this->GetY();
        $this->writeHTMLCell($half, $h, $x,           $y2, self::fmtCell($cfg["{$p}_footer_left_2"]  ?? ''), 0, 0, false, true, 'L');
        $this->writeHTMLCell($half, $h, $x + $half,   $y2, self::fmtCell($cfg["{$p}_footer_right_2"] ?? ''), 0, 0, false, true, 'R');

        $page = $this->getPage();
        if (
            $page > 0
            && ((int)($cfg['show_qr'] ?? 0) === 1)
            && isset($this->qr_per_page[$page])
        ) {
            $qr_size = 18;
            $x = ($this->getPageWidth() - $qr_size) / 2;
            $y = $this->getPageHeight() - 25;

            $this->write2DBarcode(
                $this->qr_per_page[$page],
                'QRCODE,M',
                $x, $y,
                $qr_size, $qr_size,
                ['border' => 0, 'padding' => 1, 'fgcolor' => [0, 0, 0], 'bgcolor' => false],
                'N'
            );

            $this->Link(
                $x, $y,
                $qr_size, $qr_size,
                $this->qr_per_page[$page],
                0,
                __('Abrir ficha del activo en GLPI', 'responsivas')
            );
        }
    }
}
