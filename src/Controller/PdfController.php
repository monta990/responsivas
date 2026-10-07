<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Responsivas\Exception\MissingEntityLocationException;
use GlpiPlugin\Responsivas\Pdf\PdfBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PdfController extends AbstractController
{
    #[Route('/computer', name: 'responsivas_computer', methods: ['POST'])]
    public function computer(Request $request): Response
    {
        return $this->generate($request, 'computer');
    }

    #[Route('/printer', name: 'responsivas_printer', methods: ['POST'])]
    public function printer(Request $request): Response
    {
        return $this->generate($request, 'printer');
    }

    #[Route('/phone', name: 'responsivas_phone', methods: ['POST'])]
    public function phone(Request $request): Response
    {
        return $this->generate($request, 'phone');
    }

    private function generate(Request $request, string $type): Response
    {
        \Session::checkLoginUser();
        if (!\Session::haveRight('user', READ)) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        // PDF generation is a state-changing request because it records the download in history.
        // Read the target user from POST data; the query fallback keeps compatibility with
        // POST callers that already send the identifier in the query string.
        $userId = (int)$request->request->get(
            'users_id',
            $request->request->get('id', $request->query->get('users_id', $request->query->get('id', 0)))
        );
        if ($userId <= 0) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException(
                __('Invalid user.', 'responsivas')
            );
        }

        $user = new \User();
        if (!$user->getFromDB($userId) || !$user->canView()) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        try {
            $method = match ($type) {
                'computer' => 'buildComputerPdf',
                'printer'  => 'buildPrinterPdf',
                'phone'    => 'buildPhonePdf',
            };
            $result = PdfBuilder::$method($userId);
        } catch (MissingEntityLocationException $e) {
            \Session::addMessageAfterRedirect($e->getMessage(), false, WARNING);
            return new \Symfony\Component\HttpFoundation\RedirectResponse(
                rtrim($GLOBALS['CFG_GLPI']['root_doc'] ?? '', '/') . '/front/user.form.php?id=' . $userId
            );
        } catch (\RuntimeException $e) {
            \Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
            return new \Symfony\Component\HttpFoundation\RedirectResponse(
                rtrim($GLOBALS['CFG_GLPI']['root_doc'] ?? '', '/') . '/front/user.form.php?id=' . $userId
            );
        }

        $history = $type . ' PDF downloaded';
        \Log::history(
            $userId,
            'User',
            [0, '', ''],
            'responsivas: ' . $history,
            \Log::HISTORY_LOG_SIMPLE_MESSAGE
        );

        $filename = $result['filename'];
        $content = $result['pdf']->Output($filename, 'S');

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
