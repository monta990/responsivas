<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Responsivas\Exception\MissingEntityLocationException;
use GlpiPlugin\Responsivas\Pdf\PdfBuilder;
use GlpiPlugin\Responsivas\Paths;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PreviewController extends AbstractController
{
    #[Route('/preview', name: 'responsivas_preview', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        \Session::checkLoginUser();
        if (!\Session::haveRight('config', UPDATE)) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        $type = (string)$request->query->get('type', '');
        if (!in_array($type, ['pc', 'pri', 'pho'], true)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException();
        }

        $config = \Config::getConfigurationValues('plugin_responsivas');
        $adminId = (int)\Session::getLoginUserID();

        try {
            $result = PdfBuilder::buildPreview($type, $adminId, $config);
        } catch (MissingEntityLocationException $e) {
            \Session::addMessageAfterRedirect($e->getMessage(), false, WARNING);
            return new \Symfony\Component\HttpFoundation\RedirectResponse(
                Paths::routeUrl('config')
            );
        } catch (\RuntimeException $e) {
            \Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
            return new \Symfony\Component\HttpFoundation\RedirectResponse(
                Paths::routeUrl('config')
            );
        } catch (\Throwable $e) {
            \Toolbox::logInFile('responsivas', '[preview] ' . $e->getMessage() . "\n");
            \Session::addMessageAfterRedirect(
                __('Error generating the preview. Check the GLPI error log for details.', 'responsivas'),
                false,
                ERROR
            );
            return new \Symfony\Component\HttpFoundation\RedirectResponse(
                Paths::routeUrl('config')
            );
        }

        $wmText = trim($config['watermark_text'] ?? '');
        $prefix = $wmText !== '' ? $wmText : __('Preview', 'responsivas');
        $prefix = preg_replace('/[^A-Za-z0-9_\-]/', '_', $prefix);
        $filename = $prefix . '_' . $result['filename'];

        $content = $result['pdf']->Output($filename, 'S');
        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
