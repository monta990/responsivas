<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas;

use GlpiPlugin\Responsivas\Pdf\PdfBuilder;

/**
 * Generator
 *
 * Genera los PDFs como cadenas binarias para adjuntarlos en correos.
 * Delega toda la lógica de construcción a PdfBuilder,
 * que es la fuente única de verdad del contenido de cada PDF.
 */
class Generator
{
   /**
    * Generates only the selected PDF types for the user.
    *
    * @param int   $user_id
    * @param array $types  Array of enabled types: ['computers'=>bool, 'printers'=>bool, 'phones'=>bool]
    * @return array
    */
   public static function generateSelected(int $user_id, array $types): array
   {
      PdfBuilder::validateActiveEntityLocation();

      $pdfs    = [];
      $methods = [];

      if (!empty($types['computers'])) {
         $methods[] = [PdfBuilder::class, 'buildComputerPdf'];
      }
      if (!empty($types['printers'])) {
         $methods[] = [PdfBuilder::class, 'buildPrinterPdf'];
      }
      if (!empty($types['phones'])) {
         $methods[] = [PdfBuilder::class, 'buildPhonePdf'];
      }

      foreach ($methods as $callable) {
         try {
            $result = $callable($user_id);
            $pdfs[] = [
               'filename' => $result['filename'],
               'content'  => $result['pdf']->Output('', 'S'),
            ];
         } catch (\RuntimeException $e) {
            // No assets of this type or incomplete config — skip silently
         }
      }

      return $pdfs;
   }

}