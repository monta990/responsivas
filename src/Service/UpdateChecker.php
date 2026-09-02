<?php
declare(strict_types=1);

namespace GlpiPlugin\Responsivas\Service;

use GlpiPlugin\Responsivas\Paths;

final class UpdateChecker
{
    public static function latest(): ?string
    {
        $cacheDir = Paths::filesDir();
        $cache = $cacheDir . '/release_cache.json';
        $ttl = 21600;
        $staleVersion = null;

        if (is_file($cache) && is_readable($cache)) {
            $cached = json_decode((string)@file_get_contents($cache), true);
            if (is_array($cached) && !empty($cached['version'])) {
                $candidate = (string)$cached['version'];
                if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $candidate)) {
                    $staleVersion = $candidate;
                    if (!empty($cached['checked_at']) && (time() - (int)$cached['checked_at']) < $ttl) {
                        return $candidate;
                    }
                }
            }
        }

        $url = 'https://api.github.com/repos/monta990/responsivas/releases/latest';
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
                'timeout' => 4,
                'ignore_errors' => true,
            ],
        ]);

        $json = @file_get_contents($url, false, $context, 0, 65536);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
                $status = (int)$m[1];
            }
        }
        if ($json === false || $status !== 200) {
            return $staleVersion;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
            return $staleVersion;
        }

        $version = preg_replace('/^[vV]/', '', trim((string)$data['tag_name']));
        if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return $staleVersion;
        }

        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0750, true) && !is_dir($cacheDir)) {
            return $version;
        }

        $payload = json_encode(['checked_at' => time(), 'version' => $version], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($payload)) {
            $tmp = $cache . '.' . bin2hex(random_bytes(6)) . '.tmp';
            if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
                @chmod($tmp, 0640);
                if (!@rename($tmp, $cache)) {
                    @unlink($tmp);
                }
            }
        }
        return $version;
    }
}
