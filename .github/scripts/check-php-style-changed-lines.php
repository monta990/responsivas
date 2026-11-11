<?php
declare(strict_types=1);

/**
 * Enforce PHP-CS-Fixer only on lines introduced or changed by the current PR.
 *
 * The repository has a measured legacy formatting baseline. This script runs
 * the official fixer against temporary copies of changed runtime files, then
 * intersects its proposed changes with the PR's added-line ranges. Existing
 * formatting debt elsewhere in those files is reported but does not block a
 * focused bug-fix PR.
 */

$root = getenv('GITHUB_WORKSPACE') ?: dirname(__DIR__, 2);
$baseSha = $argv[1] ?? '';
$headSha = $argv[2] ?? '';
$tempRoot = $argv[3] ?? '';
$paths = array_slice($argv, 4);

if ($baseSha === '' || $headSha === '' || $tempRoot === '' || $paths === []) {
    fwrite(STDERR, "Usage: check-php-style-changed-lines.php BASE_SHA HEAD_SHA TEMP_ROOT FILE...\n");
    exit(2);
}

if (!is_dir($root) || !is_dir($tempRoot)) {
    fwrite(STDERR, "Repository root or temporary style-check directory does not exist.\n");
    exit(2);
}

/** @return array{0: int, 1: string} */
$run = static function (array $arguments): array {
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    exec($command . ' 2>&1', $output, $status);

    return [$status, implode("\n", $output)];
};

/** @return array<int, array{old_start:int, old_count:int, new_start:int, new_count:int}> */
$readHunks = static function (string $diff): array {
    preg_match_all(
        '/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/m',
        $diff,
        $matches,
        PREG_SET_ORDER
    );

    $hunks = [];
    foreach ($matches as $match) {
        $hunks[] = [
            'old_start' => (int) $match[1],
            'old_count' => isset($match[2]) && $match[2] !== '' ? (int) $match[2] : 1,
            'new_start' => (int) $match[3],
            'new_count' => isset($match[4]) && $match[4] !== '' ? (int) $match[4] : 1,
        ];
    }

    return $hunks;
};

$violations = [];
$legacyOnly = [];

foreach ($paths as $path) {
    $sourcePath = $root . DIRECTORY_SEPARATOR . $path;
    $formattedPath = $tempRoot . DIRECTORY_SEPARATOR . $path;

    if (!is_file($sourcePath) || !is_file($formattedPath)) {
        fwrite(STDERR, sprintf("Missing source or formatted copy for %s.\n", $path));
        exit(2);
    }

    [$prStatus, $prDiff] = $run([
        'git',
        '-C',
        $root,
        'diff',
        '--no-ext-diff',
        '--unified=0',
        $baseSha,
        $headSha,
        '--',
        $path,
    ]);

    if ($prStatus !== 0) {
        fwrite(STDERR, sprintf("Unable to inspect the PR diff for %s:\n%s\n", $path, $prDiff));
        exit(2);
    }

    $changedRanges = [];
    foreach ($readHunks($prDiff) as $hunk) {
        if ($hunk['new_count'] > 0) {
            $changedRanges[] = [
                'start' => $hunk['new_start'],
                'end' => $hunk['new_start'] + $hunk['new_count'] - 1,
            ];
        }
    }

    [$styleStatus, $styleDiff] = $run([
        'git',
        'diff',
        '--no-index',
        '--no-ext-diff',
        '--unified=0',
        $sourcePath,
        $formattedPath,
    ]);

    if ($styleStatus === 0) {
        continue;
    }
    if ($styleStatus !== 1) {
        fwrite(STDERR, sprintf("Unable to inspect PHP-CS-Fixer output for %s:\n%s\n", $path, $styleDiff));
        exit(2);
    }

    $fileHasNewViolation = false;
    foreach ($readHunks($styleDiff) as $hunk) {
        foreach ($changedRanges as $range) {
            if ($hunk['old_count'] > 0) {
                $styleEnd = $hunk['old_start'] + $hunk['old_count'] - 1;
                $overlaps = max($hunk['old_start'], $range['start']) <= min($styleEnd, $range['end']);
            } else {
                // A zero-length old range is an insertion after old_start.
                $overlaps = $hunk['old_start'] >= $range['start'] - 1
                    && $hunk['old_start'] <= $range['end'];
            }

            if ($overlaps) {
                $violations[] = sprintf(
                    '%s: PHP-CS-Fixer would change PR lines %d-%d.',
                    $path,
                    $range['start'],
                    $range['end']
                );
                $fileHasNewViolation = true;
                break 2;
            }
        }
    }

    if (!$fileHasNewViolation) {
        $legacyOnly[] = $path;
    }
}

if ($violations !== []) {
    fwrite(STDERR, "PHP style violations in changed PR lines:\n - " . implode("\n - ", $violations) . "\n");
    exit(1);
}

if ($legacyOnly !== []) {
    fwrite(STDOUT, "Pre-existing formatting outside changed PR lines was ignored in: " . implode(', ', $legacyOnly) . "\n");
}

fwrite(STDOUT, sprintf("PHP-CS-Fixer changed-line check passed for %d runtime file(s).\n", count($paths)));
