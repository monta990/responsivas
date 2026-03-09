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

    public string $fecha_header = '';
    public string $location     = '';

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
    }

    public function Footer(): void {
        $this->SetY(-20);

        $cfg = Config::getConfigurationValues('plugin_responsivas');
        $p   = $this->footer_prefix;

        $this->SetFont(
            Config::getConfigurationValue('core', 'pdffont'),
            '',
            (int)($cfg[$this->font_size_key] ?? 10)
        );

        $this->Cell(90, 5, $cfg["{$p}_footer_left_1"]  ?? '', 0, 0, 'L');
        $this->Cell(0,  5, $cfg["{$p}_footer_right_1"] ?? '', 0, 1, 'R');
        $this->Cell(90, 5, $cfg["{$p}_footer_left_2"]  ?? '', 0, 0, 'L');
        $this->Cell(0,  5, $cfg["{$p}_footer_right_2"] ?? '', 0, 0, 'R');

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
