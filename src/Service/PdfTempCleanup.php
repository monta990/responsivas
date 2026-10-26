<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Service;

/**
 * Owns the short-lived files used by the shared browser PDF delivery flow.
 * New files use exclusive creation and carry their expiry in the filename so
 * they can be cleaned even when the originating GLPI session no longer exists.
 */
final class PdfTempCleanup
{
    private const PREFIX = 'responsivas_pdf_';
    public const FILE_TTL = 300;
    private const LEGACY_GRACE = 900;

    public static function savePdf(string $content, int $expires): string
    {
        $directory = rtrim(sys_get_temp_dir(), '/\\');
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $name = self::PREFIX . $expires . '_' . bin2hex(random_bytes(32)) . '.tmp';
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            $handle = @fopen($path, 'x+b');
            if ($handle === false) {
                continue;
            }

            $success = false;
            try {
                // POSIX temporary files must remain owner-only; Windows relies on the temp-directory ACL.
                if (DIRECTORY_SEPARATOR === '/' && !@chmod($path, 0600)) {
                    throw new \RuntimeException(__('Could not create a temporary PDF file.', 'responsivas'));
                }
                $length = strlen($content);
                $offset = 0;
                while ($offset < $length) {
                    $written = fwrite($handle, substr($content, $offset));
                    if ($written === false || $written === 0) {
                        throw new \RuntimeException(__('Could not create a temporary PDF file.', 'responsivas'));
                    }
                    $offset += $written;
                }
                if (!fflush($handle)) {
                    throw new \RuntimeException(__('Could not create a temporary PDF file.', 'responsivas'));
                }
                $success = true;
            } finally {
                fclose($handle);
                if (!$success) {
                    @unlink($path);
                }
            }

            if ($success && is_file($path) && !is_link($path) && filesize($path) === strlen($content)) {
                return $path;
            }
            @unlink($path);
        }

        throw new \RuntimeException(__('Could not create a temporary PDF file.', 'responsivas'));
    }

    public static function isManagedPath(string $path): bool
    {
        $directory = realpath(sys_get_temp_dir());
        $pathDirectory = realpath(dirname($path));
        if ($directory === false || $pathDirectory === false || $pathDirectory !== $directory || is_link($path)) {
            return false;
        }

        $name = basename($path);
        return preg_match('/^responsivas_pdf_\d{10}_[a-f0-9]{64}\.tmp$/D', $name) === 1
            || preg_match('/^responsivas_[A-Za-z0-9]{6,32}$/D', $name) === 1;
    }

    /**
     * Remove every expired file owned by Responsivas, not just files attached
     * to the current session. Also clean stale files created by the old 1.6.5
     * implementation; never touch files with an unrecognized name.
     */
    public static function cleanupExpiredFiles(): int
    {
        $directory = rtrim(sys_get_temp_dir(), '/\\');
        $now = time();
        $removed = 0;

        foreach ((glob($directory . DIRECTORY_SEPARATOR . self::PREFIX . '*.tmp', GLOB_NOSORT) ?: []) as $path) {
            $name = basename($path);
            if (preg_match('/^responsivas_pdf_(\d{10})_([a-f0-9]{64})\.tmp$/D', $name, $matches) !== 1) {
                continue;
            }
            if ((int)$matches[1] > $now || (!is_file($path) && !is_link($path))) {
                continue;
            }
            if (@unlink($path) && !file_exists($path) && !is_link($path)) {
                ++$removed;
            }
        }

        // Compatibility cleanup for files created by tempnam(..., 'responsivas_')
        // before files became expiry-addressable. The 15-minute grace is three
        // times the previous five-minute delivery-token lifetime.
        foreach ((glob($directory . DIRECTORY_SEPARATOR . 'responsivas_*', GLOB_NOSORT) ?: []) as $path) {
            if (preg_match('/^responsivas_[A-Za-z0-9]{6,32}$/D', basename($path)) !== 1) {
                continue;
            }
            $mtime = @filemtime($path);
            if ($mtime === false || $mtime > ($now - self::LEGACY_GRACE)) {
                continue;
            }
            if (!is_file($path) && !is_link($path)) {
                continue;
            }
            if (@unlink($path) && !file_exists($path) && !is_link($path)) {
                ++$removed;
            }
        }

        return $removed;
    }

    public static function cronCleanExpiredPdfs(\CronTask $task): int
    {
        $removed = self::cleanupExpiredFiles();
        if ($removed > 0) {
            $task->addVolume($removed);
            $task->log(sprintf(
                _n(
                    'Removed %d expired temporary PDF file.',
                    'Removed %d expired temporary PDF files.',
                    $removed,
                    'responsivas'
                ),
                $removed
            ));
            return 1;
        }
        return 0;
    }

    public static function cronInfo(string $name): array
    {
        if ($name !== 'CleanExpiredPdfs') {
            return [];
        }
        return [
            'description' => __('Clean expired temporary PDF files generated by Responsivas', 'responsivas'),
        ];
    }
}
