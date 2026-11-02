<?php

declare(strict_types=1);

/**
 * Validate a GLPI plugin's PSR-4 namespace/path contract using Composer's
 * ClassLoader. This mirrors GLPI's GlpiPlugin namespace-to-src mapping without
 * booting GLPI or executing plugin classes that need a live GLPI runtime.
 *
 * Usage: php check-psr4.php <src-directory> <namespace-prefix> <vendor-autoload.php>
 */

function fail(string $message): void
{
    fwrite(STDERR, "[PSR-4] ERROR: {$message}\n");
}

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php {$argv[0]} <src-directory> <namespace-prefix> <vendor-autoload.php>\n");
    exit(2);
}

$sourceDirectory = realpath($argv[1]);
$namespacePrefix = trim($argv[2], "\\");
$autoloadFile = realpath($argv[3]);

if ($sourceDirectory === false || !is_dir($sourceDirectory)) {
    fail("Source directory does not exist: {$argv[1]}");
    exit(2);
}
if ($namespacePrefix === '') {
    fail('Namespace prefix must not be empty.');
    exit(2);
}
if ($autoloadFile === false || !is_file($autoloadFile)) {
    fail("Composer autoloader does not exist: {$argv[3]}");
    exit(2);
}

require_once $autoloadFile;

if (!class_exists(\Composer\Autoload\ClassLoader::class)) {
    fail('Composer ClassLoader is unavailable from the supplied GLPI autoloader.');
    exit(2);
}

$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4($namespacePrefix . '\\', $sourceDirectory . DIRECTORY_SEPARATOR);
$loader->register(true);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS)
);
$phpFiles = [];
$declarations = [];
$failures = 0;

$classLikeTokens = [T_CLASS, T_INTERFACE, T_TRAIT];
if (defined('T_ENUM')) {
    $classLikeTokens[] = T_ENUM;
}

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $filePath = $fileInfo->getRealPath();
    if ($filePath === false) {
        fail('Unable to resolve a PHP source path.');
        $failures++;
        continue;
    }

    $phpFiles[] = $filePath;
    $relativePath = str_replace(
        DIRECTORY_SEPARATOR,
        '/',
        substr($filePath, strlen($sourceDirectory) + 1)
    );
    $relativeDirectory = dirname($relativePath);
    $expectedNamespace = $namespacePrefix;
    if ($relativeDirectory !== '.') {
        $expectedNamespace .= '\\' . str_replace('/', '\\', $relativeDirectory);
    }

    try {
        $tokens = token_get_all((string) file_get_contents($filePath), TOKEN_PARSE);
    } catch (ParseError $exception) {
        fail("PHP parse error in {$relativePath}: {$exception->getMessage()}");
        $failures++;
        continue;
    }

    $currentNamespace = '';
    $previousSignificantToken = null;
    $fileDeclarations = [];

    for ($index = 0, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        $tokenType = is_array($token) ? $token[0] : $token;

        if ($tokenType === T_NAMESPACE) {
            $namespace = '';
            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $part = $tokens[$cursor];
                if ($part === ';' || $part === '{') {
                    break;
                }
                if (
                    is_array($part)
                    && in_array($part[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    continue;
                }
                $namespace .= is_array($part) ? $part[1] : $part;
            }
            $currentNamespace = trim($namespace, '\\');
        }

        $isClassLike = in_array($tokenType, $classLikeTokens, true);
        $isClassConstant = $tokenType === T_CLASS
            && in_array($previousSignificantToken, [T_NEW, T_DOUBLE_COLON], true);

        if ($isClassLike && !$isClassConstant) {
            $symbol = null;
            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $part = $tokens[$cursor];
                if (
                    is_array($part)
                    && in_array($part[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    continue;
                }
                if (is_array($part) && $part[0] === T_STRING) {
                    $symbol = $part[1];
                }
                break;
            }

            if ($symbol !== null) {
                $fileDeclarations[] = [
                    'namespace' => $currentNamespace,
                    'name'      => $symbol,
                    'fqcn'      => $currentNamespace . '\\' . $symbol,
                ];
            }
        }

        if (
            !is_array($token)
            || !in_array($tokenType, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ) {
            $previousSignificantToken = $tokenType;
        }
    }

    if ($fileDeclarations === []) {
        fail("No named class, interface, trait or enum found in {$relativePath}.");
        $failures++;
        continue;
    }

    $fileName = pathinfo($relativePath, PATHINFO_FILENAME);
    $matchingDeclarations = array_values(array_filter(
        $fileDeclarations,
        static fn (array $declaration): bool => $declaration['name'] === $fileName
    ));

    if ($matchingDeclarations === []) {
        $declaredNames = implode(', ', array_column($fileDeclarations, 'name'));
        fail("File {$relativePath} must declare a PSR-4 symbol named {$fileName}; found {$declaredNames}.");
        $failures++;
        continue;
    }

    foreach ($fileDeclarations as $declaration) {
        if ($declaration['namespace'] !== $expectedNamespace) {
            fail(
                "{$relativePath}: namespace {$declaration['namespace']} does not match "
                . "expected {$expectedNamespace}."
            );
            $failures++;
        }
    }

    foreach ($matchingDeclarations as $declaration) {
        $resolvedFile = $loader->findFile($declaration['fqcn']);
        $resolvedPath = $resolvedFile !== false ? realpath($resolvedFile) : false;

        if ($resolvedPath !== $filePath) {
            fail(
                "{$declaration['fqcn']} resolves to "
                . ($resolvedPath === false ? 'no file' : $resolvedPath)
                . "; expected {$filePath}."
            );
            $failures++;
        } else {
            $declarations[] = $declaration['fqcn'];
        }
    }
}

sort($phpFiles);
sort($declarations);

if ($phpFiles === []) {
    fail("No PHP files found under {$sourceDirectory}.");
    exit(1);
}

if ($failures > 0) {
    fwrite(STDERR, sprintf("[PSR-4] %d failure(s) across %d PHP file(s).\n", $failures, count($phpFiles)));
    exit(1);
}

printf(
    "[PSR-4] OK: Composer resolved %d class(es) from %d PHP file(s) using namespace %s and source %s.\n",
    count($declarations),
    count($phpFiles),
    $namespacePrefix,
    $sourceDirectory
);
