<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Responsivas\Exception\MissingEntityLocationException;
use GlpiPlugin\Responsivas\Paths;
use GlpiPlugin\Responsivas\Pdf\PdfBuilder;
use GlpiPlugin\Responsivas\Service\PdfTempCleanup;
use GlpiPlugin\Responsivas\Service\PdfDeliveryService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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

    #[Route('/pdf/{token}/{filename}', name: 'responsivas_pdf_file', methods: ['GET', 'HEAD'], requirements: ['token' => '[A-Fa-f0-9]{64}', 'filename' => '[^/]+'])]
    public function file(Request $request, string $token, string $filename): Response
    {
        \Session::checkLoginUser();
        if (!\Session::haveRight('user', READ)) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        // Opportunistic cleanup complements the scheduled global cleanup task.
        self::cleanupPdfTokens();

        $tokens = $_SESSION['plugin_responsivas_pdf_tokens'] ?? [];
        if (!is_array($tokens) || !isset($tokens[$token]) || !is_array($tokens[$token])) {
            // An absent or expired ticket is an expected lifecycle condition.
            // Show the translated recovery page instead of a Symfony trace.
            return self::pdfUnavailableResponse();
        }

        $record = $tokens[$token];
        $path = (string)($record['path'] ?? '');
        $storedFilename = (string)($record['filename'] ?? '');
        $userId = (int)($record['user_id'] ?? 0);
        $type = (string)($record['type'] ?? '');
        // Missing `kind` identifies a still-live ticket created before this
        // update; keep those responsibility links compatible during rollout.
        $kind = (string)($record['kind'] ?? 'responsibility');
        $movement = (string)($record['movement'] ?? '');
        $fileSize = ($path !== '' && PdfTempCleanup::isManagedPath($path) && is_file($path))
            ? @filesize($path)
            : false;

        if (
            ($record['expires'] ?? 0) <= time()
            || $path === ''
            || !PdfTempCleanup::isManagedPath($path)
            || $storedFilename === ''
            || $filename !== $storedFilename
            || $userId <= 0
            || !in_array($type, ['computer', 'printer', 'phone'], true)
            || !in_array($kind, ['responsibility', 'manual'], true)
            || ($kind === 'manual' && !in_array($movement, ['inspection', 'return'], true))
            || !is_file($path)
            || $fileSize === false
            || $fileSize <= 0
        ) {
            unset($_SESSION['plugin_responsivas_pdf_tokens'][$token]);
            if ($path !== '' && PdfTempCleanup::isManagedPath($path) && is_file($path)) {
                @unlink($path);
            }
            return self::pdfUnavailableResponse();
        }

        // Recheck access to the specific target user on every file request,
        // not just when a PDF ticket is first created. This matters because
        // reusable tickets remain valid until their short expiry time.
        $targetUser = new \User();
        $targetExists = $targetUser->getFromDB($userId);
        $canReadTarget = $targetExists && (
            $kind === 'manual'
                ? $targetUser->can($userId, READ)
                : $targetUser->canView()
        );
        if (!$canReadTarget) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string)$fileSize,
        ] + Paths::privatePdfHeaders();

        // HEAD probes and repeated GETs can safely use the same ticket until
        // expiry. Keeping the file also makes browser refresh/reopen work.
        if ($request->isMethod('HEAD')) {
            $headResponse = new Response('', 200, $headers);
            self::setInlineFilename($headResponse, $storedFilename);
            return $headResponse;
        }

        // Validate only the PDF signature here. BinaryFileResponse streams the
        // managed temporary file and supplies the correct PDF content headers.
        $signature = @file_get_contents($path, false, null, 0, 5);
        if ($signature !== '%PDF-') {
            unset($_SESSION['plugin_responsivas_pdf_tokens'][$token]);
            if (PdfTempCleanup::isManagedPath($path) && is_file($path)) {
                @unlink($path);
            }
            return self::pdfUnavailableResponse();
        }

        // Responsibility documents keep their historical audit event, but a
        // reusable ticket is logged only after its first successful response.
        // Manual inspection/return forms did not previously create this event.
        $history = null;
        if ($kind === 'responsibility') {
            $typeLabels = [
                'computer' => __('Computer', 'responsivas'),
                'printer'  => __('Printer', 'responsivas'),
                'phone'    => __('Phone', 'responsivas'),
            ];
            $history = sprintf(
                __('Responsibility PDF for %s downloaded.', 'responsivas'),
                $typeLabels[$type]
            );
        }

        return new PdfDownloadResponse(
            $path,
            $storedFilename,
            $userId,
            $history,
            $token,
            200,
            $headers
        );
    }

    /**
     * Return a translated, user-friendly response for a missing, expired, or
     * invalid PDF ticket. This is an expected lifecycle state, not an
     * authorization exception, so it should not expose a Symfony stack trace.
     */
    private static function pdfUnavailableResponse(): Response
    {
        $language = str_replace('_', '-', (string)($_SESSION['glpilanguage'] ?? 'en'));
        $language = preg_replace('/[^a-zA-Z0-9-]/', '', $language) ?: 'en';

        $title = htmlspecialchars(__('PDF unavailable', 'responsivas'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars(
            __('This PDF link is no longer available or has already been used.', 'responsivas'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $guidance = htmlspecialchars(
            __('Return to the corresponding record and generate the PDF again.', 'responsivas'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $html = <<<HTML
<!doctype html>
<html lang="{$language}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f5f6f8;
            color: #273142;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.55;
        }
        main {
            width: 100%;
            max-width: 560px;
            padding: 32px;
            border: 1px solid #e0e4ea;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 8px 28px rgb(31 41 55 / 8%);
        }
        h1 { margin: 0 0 16px; font-size: 1.5rem; line-height: 1.25; }
        p { margin: 0 0 12px; }
        p:last-child { margin-bottom: 0; }
        @media (max-width: 480px) { main { padding: 24px; } }
    </style>
</head>
<body>
    <main role="main">
        <h1>{$title}</h1>
        <p>{$message}</p>
        <p>{$guidance}</p>
    </main>
</body>
</html>
HTML;

        return new Response($html, Response::HTTP_GONE, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
            'Referrer-Policy' => 'no-referrer',
        ] + Paths::privatePdfHeaders());
    }

    /**
     * Set the PDF inline filename using APIs available on GLPI 11 and GLPI 12.
     * `setContentDisposition()` belongs to BinaryFileResponse, not Response.
     */
    private static function setInlineFilename(Response $response, string $filename): void
    {
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename)
        );
    }

    /**
     * Clean expired session tickets and all expired files owned by Responsivas.
     */
    private static function cleanupPdfTokens(): void
    {
        $tokens = $_SESSION['plugin_responsivas_pdf_tokens'] ?? [];
        if (!is_array($tokens)) {
            $_SESSION['plugin_responsivas_pdf_tokens'] = [];
        } else {
            $now = time();
            foreach ($tokens as $token => $record) {
                if (!is_array($record) || (int)($record['expires'] ?? 0) > $now) {
                    continue;
                }

                $path = (string)($record['path'] ?? '');
                if ($path !== '' && PdfTempCleanup::isManagedPath($path) && is_file($path)) {
                    @unlink($path);
                }
                unset($_SESSION['plugin_responsivas_pdf_tokens'][$token]);
            }
        }

        PdfTempCleanup::cleanupExpiredFiles();
    }

    private function generate(Request $request, string $type): Response
    {
        \Session::checkLoginUser();
        if (!\Session::haveRight('user', READ)) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException();
        }

        self::cleanupPdfTokens();

        // PDF generation is a POST action. Its history entry is written only
        // after the later GET has streamed the file to the response.
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
            return new RedirectResponse(
                rtrim($GLOBALS['CFG_GLPI']['root_doc'] ?? '', '/') . '/front/user.form.php?id=' . $userId
            );
        } catch (\RuntimeException $e) {
            \Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
            return new RedirectResponse(
                rtrim($GLOBALS['CFG_GLPI']['root_doc'] ?? '', '/') . '/front/user.form.php?id=' . $userId
            );
        }

        $filename = (string)$result['filename'];
        $content = $result['pdf']->Output($filename, 'S');
        if (!is_string($content) || substr($content, 0, 5) !== '%PDF-') {
            throw new \RuntimeException(__('Could not generate the PDF.', 'responsivas'));
        }

        // Both responsibility PDFs and manual forms are delivered through
        // the same session-bound temporary-file route, with the original PDF
        // bytes, filename, metadata, layout and configured options preserved.
        return PdfDeliveryService::issue(
            $content,
            $filename,
            $userId,
            $type,
            'responsibility'
        );
    }
}
