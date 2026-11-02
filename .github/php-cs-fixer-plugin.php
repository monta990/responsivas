<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$sourceRoot = getenv('PLUGIN_SOURCE_ROOT');
if ($sourceRoot === false || !is_dir($sourceRoot)) {
    throw new RuntimeException('PLUGIN_SOURCE_ROOT must point to the plugin repository root.');
}

$finder = Finder::create()
    ->files()
    ->name('*.php')
    ->in($sourceRoot)
    ->exclude([
        '.git',
        '.github',
        'glpi',
        'vendor',
        'node_modules',
        'dist',
        'files',
        'cache',
    ]);

return (new Config())
    ->setRules([
        '@PER-CS3x0' => true,
        'fully_qualified_strict_types' => ['import_symbols' => true],
        'ordered_imports' => ['imports_order' => ['class', 'const', 'function']],
        'no_unused_imports' => true,
        'heredoc_indentation' => false,
        'phpdoc_scalar' => true,
        'phpdoc_types' => true,
    ])
    ->setFinder($finder);
