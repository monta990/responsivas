<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Responsivas\Exception\MissingEntityLocationException;
use GlpiPlugin\Responsivas\Paths;
use GlpiPlugin\Responsivas\Pdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    #[Route('/pdf/{token}/{filename}', name: 'responsivas_pdf_file', methods: ['GET'], requirements: ['token' => '[A-Fa-f0-9]{64}', 'filename' => '[^/]+'])]
    public function file(string $token, string $filename): Response
    {
        \Session::checkLoginUser();
        if (!\Session::haveRight('user', READ)) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        $tokens = $_SESSION['plugin_responsivas_pdf_tokens'] ?? [];
        if (!is_array($tokens) || !isset($tokens[$token]) || !is_array($tokens[$token])) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        $record = $tokens[$token];
        if (($record['expires'] ?? 0) < time()) {
            unset($_SESSION['plugin_responsivas_pdf_tokens'][$token]);
            if (!empty($record['path']) && is_file($record['path'])) {
                @unlink($record['path']);
            }
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        $path = (string)($record['path'] ?? '');
        $storedFilename = (string)($record['filename'] ?? '');
        if ($path === '' || $storedFilename === '' || $filename !== $storedFilename || !is_file($path)) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        // The GET endpoint only serves a previously generated, session-bound file.
        // Logging is done by the POST generator, so this request has no side effects.
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition('inline', $storedFilename);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Cache-Control', 'private, max-age=300, must-revalidate');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * Remove expired temporary PDF tickets/files from the current session.
     */
    private static function cleanupPdfTokens(): void
    {
        $tokens = $_SESSION['plugin_responsivas_pdf_tokens'] ?? [];
        if (!is_array($tokens)) {
            $_SESSION['plugin_responsivas_pdf_tokens'] = [];
            return;
        }

        $now = time();
        foreach ($tokens as $token => $record) {
            if (!is_array($record) || ($record['expires'] ?? 0) >= $now) {
                continue;
            }

            $path = (string)($record['path'] ?? '');
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
            unset($_SESSION['plugin_responsivas_pdf_tokens'][$token]);
        }
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

        self::cleanupPdfTokens();

        $filename = $result['filename'];
        $tempFile = tempnam(sys_get_temp_dir(), 'responsivas_');
        if ($tempFile === false) {
            throw new \RuntimeException(__('Could not create a temporary PDF file.', 'responsivas'));
        }

        try {
            // Generate the PDF in memory first. TCPDF 7.0.x before 7.0.10 had a
            // compatibility bug in the F destination: the full file path was
            // passed to tc-lib-pdf as an output directory. Output(..., 'S') is
            // supported by both TCPDF 6.11 (GLPI 11) and TCPDF 7 (GLPI 12).
            $content = $result['pdf']->Output($filename, 'S');
            if (!is_string($content) || substr($content, 0, 5) !== '%PDF-') {
                throw new \RuntimeException(__('Could not generate the PDF.', 'responsivas'));
            }

            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                throw new \RuntimeException(__('Could not save the generated PDF.', 'responsivas'));
            }
        } catch (\Throwable $e) {
            @unlink($tempFile);
            throw new \RuntimeException(__('Could not generate the PDF.', 'responsivas'), 0, $e);
        }

        if (!is_file($tempFile) || filesize($tempFile) <= 0) {
            @unlink($tempFile);
            throw new \RuntimeException(__('Could not generate the PDF.', 'responsivas'));
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['plugin_responsivas_pdf_tokens'][$token] = [
            'path' => $tempFile,
            'filename' => $filename,
            'expires' => time() + 300,
        ];

        $history = $type . ' PDF downloaded';
        \Log::history(
            $userId,
            'User',
            [0, '', ''],
            'responsivas: ' . $history,
            \Log::HISTORY_LOG_SIMPLE_MESSAGE
        );

        // Finish the POST with a 303 so the new tab follows a normal GET to a
        // URL that contains the real PDF filename. This restores the 1.6.2
        // browser Save As behaviour while keeping CSRF protection on generation.
        $pdfUrl = Paths::routeUrl('pdf/' . rawurlencode($token) . '/' . rawurlencode($filename));
        return new RedirectResponse($pdfUrl, 303);
    }
}
