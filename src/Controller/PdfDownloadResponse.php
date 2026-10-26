<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Streams a temporary PDF file with Symfony's binary-file response handling.
 * The responsibility history event is recorded only after a successful send.
 * Reusable tickets remain available until their short TTL, so browser refreshes
 * can reopen the same file without generating duplicate audit entries.
 */
final class PdfDownloadResponse extends BinaryFileResponse
{
    private bool $historyAttempted = false;

    public function __construct(
        string $path,
        string $filename,
        private readonly int $targetUserId,
        private readonly ?string $historyMessage,
        private readonly string $ticketToken,
        int $status = 200,
        array $headers = []
    ) {
        // Disable automatic validators: each request is private and non-cacheable.
        parent::__construct($path, $status, $headers, false, null, false, false);

        // Force the MIME type instead of deriving it from the temporary path.
        $this->headers->set('Content-Type', 'application/pdf');
        $this->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
    }

    public function send($flush = true): static
    {
        parent::send($flush);

        if (
            !$flush
            || $this->historyAttempted
            || $this->historyMessage === null
            || connection_aborted() !== 0
            || connection_status() !== CONNECTION_NORMAL
        ) {
            return $this;
        }

        $sessionKey = 'plugin_responsivas_pdf_tokens';
        $tokens = $_SESSION[$sessionKey] ?? null;
        if (
            !is_array($tokens)
            || !isset($tokens[$this->ticketToken])
            || !is_array($tokens[$this->ticketToken])
            || !empty($tokens[$this->ticketToken]['history_recorded'])
        ) {
            return $this;
        }

        // Mark before attempting the log to avoid duplicate entries if the
        // response lifecycle invokes send() more than once in the same request.
        $_SESSION[$sessionKey][$this->ticketToken]['history_recorded'] = true;
        $this->historyAttempted = true;

        try {
            \Log::history(
                $this->targetUserId,
                'User',
                [0, '', $this->historyMessage],
                0,
                \Log::HISTORY_LOG_SIMPLE_MESSAGE
            );
        } catch (\Throwable $e) {
            // Allow a later reload to retry the audit write if GLPI's log call
            // fails, while still avoiding duplicate writes on successful calls.
            $_SESSION[$sessionKey][$this->ticketToken]['history_recorded'] = false;
            $this->historyAttempted = false;
            \Toolbox::logInFile('responsivas', '[pdf-history] ' . $e->getMessage() . "\n");
        }

        return $this;
    }
}
