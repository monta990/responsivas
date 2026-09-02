<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Responsivas\Pdf\PdfBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class ManualFormController extends AbstractController
{
    protected function renderManual(Request $request, string $movement): Response
    {
        \Session::checkLoginUser();

        if (!\Session::haveRight('user', READ)) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        $itemtype = (string)$request->query->get('itemtype', 'Computer');
        $userId = (int)$request->query->get('users_id', 0);
        $config = \Config::getConfigurationValues('plugin_responsivas');

        $prefix = [
            'Computer' => 'pc',
            'Printer'  => 'pri',
            'Phone'    => 'pho',
        ][$itemtype] ?? null;

        if ($prefix === null) {
            throw new BadRequestHttpException(__('Invalid asset type.', 'responsivas'));
        }

        $enabledKey = $prefix . (
            $movement === 'return'
                ? '_enable_return_form'
                : '_enable_visual_inspection'
        );

        if ((int)($config[$enabledKey] ?? 1) !== 1) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        $baseline = ob_get_level();
        $captured = '';
        $result = null;

        ob_start();
        try {
            $result = PdfBuilder::buildManualFormPdf(
                $itemtype,
                $movement === 'return'
                    ? 'return'
                    : 'delivery',
                $userId
            );

            $filename = $result['filename'];
            $content = $result['pdf']->Output($filename, 'S');
            $captured = (string)ob_get_contents();
        } finally {
            while (ob_get_level() > $baseline) {
                ob_end_clean();
            }
        }

        if ($captured !== '') {
            \Toolbox::logInFile(
                'responsivas',
                'TCPDF output while generating manual PDF (' . $itemtype . '): '
                . trim(strip_tags($captured))
            );
        }

        if (!is_string($content ?? null) || substr($content, 0, 5) !== '%PDF-') {
            throw new \RuntimeException(__('Could not generate the manual PDF.', 'responsivas'));
        }

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
