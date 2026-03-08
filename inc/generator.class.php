<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

require_once __DIR__ . '/pdfbuilder.class.php';

/**
 * PluginResponsivasGenerator
 *
 * Genera los PDFs como cadenas binarias para adjuntarlos en correos.
 * Delega toda la lógica de construcción a PluginResponsivasPdfBuilder,
 * que es la fuente única de verdad del contenido de cada PDF.
 */
class PluginResponsivasGenerator
{
   /**
    * Genera todos los PDFs disponibles para el usuario.
    *
    * @param int $user_id
    * @return array  Array de ['filename' => string, 'content' => string]
    */
   public static function generateAll(int $user_id): array
   {
      $pdfs    = [];
      $methods = [
         ['PluginResponsivasPdfBuilder', 'buildComputerPdf'],
         ['PluginResponsivasPdfBuilder', 'buildPrinterPdf'],
         ['PluginResponsivasPdfBuilder', 'buildPhonePdf'],
      ];

      foreach ($methods as $callable) {
         try {
            $result = $callable($user_id);
            $pdfs[] = [
               'filename' => $result['filename'],
               'content'  => $result['pdf']->Output('', 'S'),
            ];
         } catch (RuntimeException $e) {
            // Sin activos de este tipo o configuración incompleta — se omite silenciosamente
         }
      }

      return $pdfs;
   }
}
