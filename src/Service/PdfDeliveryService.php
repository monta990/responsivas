<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Service;

use GlpiPlugin\Responsivas\Paths;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Creates the same short-lived, session-bound PDF delivery ticket for every
 * browser PDF flow (responsibility documents and manual forms).
 *
 * PDF construction remains the responsibility of PdfBuilder; this service
 * only stores the resulting bytes and redirects to the shared file endpoint.
 */
final class PdfDeliveryService
{
    private const SESSION_KEY = 'plugin_responsivas_pdf_tokens';
    private const ALLOWED_TYPES = ['computer', 'printer', 'phone'];
    private const ALLOWED_KINDS = ['responsibility', 'manual'];
    private const ALLOWED_MOVEMENTS = ['inspection', 'return'];

    public static function issue(
        string $content,
        string $filename,
        int $userId,
        string $type,
        string $kind,
        ?string $movement = null
    ): RedirectResponse {
        if (substr($content, 0, 5) !== '%PDF-') {
            throw new \RuntimeException(__('Could not generate the PDF.', 'responsivas'));
        }

        // The filename is displayed in Content-Disposition and used as one URL
        // segment; never allow path separators or dot-directory values here.
        if (
            $filename === ''
            || $filename === '.'
            || $filename === '..'
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
        ) {
            throw new \RuntimeException(__('Could not generate the PDF.', 'responsivas'));
        }

        if (
            $userId <= 0
            || !in_array($type, self::ALLOWED_TYPES, true)
            || !in_array($kind, self::ALLOWED_KINDS, true)
            || ($kind === 'manual' && !in_array($movement, self::ALLOWED_MOVEMENTS, true))
            || ($kind === 'responsibility' && $movement !== null)
        ) {
            throw new \RuntimeException(__('Could not generate the PDF.', 'responsivas'));
        }

        $expires = time() + PdfTempCleanup::FILE_TTL;
        $tempFile = PdfTempCleanup::savePdf($content, $expires);

        try {
            // The ticket is random, session-bound, and reusable only during
            // the short file lifetime. Its temporary file is removed on expiry.
            $token = bin2hex(random_bytes(32));
            if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
                $_SESSION[self::SESSION_KEY] = [];
            }
            while (isset($_SESSION[self::SESSION_KEY][$token])) {
                $token = bin2hex(random_bytes(32));
            }

            $_SESSION[self::SESSION_KEY][$token] = [
                'path' => $tempFile,
                'filename' => $filename,
                'user_id' => $userId,
                'type' => $type,
                'kind' => $kind,
                'movement' => $movement,
                'expires' => $expires,
                'history_recorded' => false,
            ];
        } catch (\Throwable $e) {
            @unlink($tempFile);
            throw $e;
        }

        $pdfUrl = Paths::routeUrl('pdf/' . rawurlencode($token) . '/' . rawurlencode($filename));
        $response = new RedirectResponse($pdfUrl, 303);
        $response->headers->add(Paths::privatePdfHeaders());
        return $response;
    }
}
