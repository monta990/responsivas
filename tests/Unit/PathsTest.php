<?php
declare(strict_types=1);

use GlpiPlugin\Responsivas\Paths;
use PHPUnit\Framework\TestCase;

final class PathsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $GLOBALS['CFG_GLPI'] = ['root_doc' => '/glpi/'];
    }

    public function testPluginDirectoryFallsBackToThePublishedPluginRoot(): void
    {
        self::assertSame(dirname(__DIR__, 2), Paths::pluginDir());
    }

    public function testWebDirectoryUsesTheGlpiDocumentRootWithoutDuplicateSlashes(): void
    {
        $GLOBALS['CFG_GLPI']['root_doc'] = '/glpi///';

        self::assertSame('/glpi/plugins/responsivas', Paths::webDir());
    }

    public function testRouteUrlPreservesExistingPluginRouteStructure(): void
    {
        self::assertSame('/glpi/plugins/responsivas', Paths::routeUrl());
        self::assertSame(
            '/glpi/plugins/responsivas/inspection',
            Paths::routeUrl('/inspection')
        );
        self::assertSame(
            '/glpi/plugins/responsivas/pdf/example/file.pdf',
            Paths::routeUrl('pdf/example/file.pdf')
        );
    }

    public function testManualFormSlugsRemainLocalizedAndKeepEnglishDefaults(): void
    {
        $expected = [
            'en_GB' => ['inspection', 'return'],
            'es_MX' => ['inspeccion-visual', 'devolucion'],
            'fr_FR' => ['inspection-visuelle', 'restitution'],
            'de_DE' => ['sichtpruefung', 'rueckgabe'],
            'it_IT' => ['ispezione-visiva', 'restituzione'],
        ];

        foreach ($expected as $language => [$inspection, $return]) {
            $_SESSION['glpilanguage'] = $language;

            self::assertSame($inspection, Paths::manualFormSlug('inspection'), $language);
            self::assertSame($return, Paths::manualFormSlug('return'), $language);
        }
    }

    public function testUnknownLanguageFallsBackToThePublishedEnglishSlugs(): void
    {
        $_SESSION['glpilanguage'] = 'pt_BR';

        self::assertSame('inspection', Paths::manualFormSlug('inspection'));
        self::assertSame('return', Paths::manualFormSlug('return'));
    }

    public function testManualFormSlugRejectsUnknownFormNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Paths::manualFormSlug('../inspection');
    }

    public function testEveryWhitelistedSchematicResolvesInsideThePublishedAssetsDirectory(): void
    {
        $assets = [
            'computer_general.png',
            'phone_general.png',
            'printer_general.png',
            'damage_icons/scratch.png',
            'damage_icons/impact.png',
            'damage_icons/wear.png',
            'damage_icons/missing.png',
        ];

        $expectedDirectory = Paths::pluginDir() . '/assets/schematics/';

        foreach ($assets as $asset) {
            $path = Paths::assetPath($asset);

            self::assertSame($expectedDirectory . $asset, $path);
            self::assertFileExists($path);
        }
    }

    public function testAssetPathRejectsTraversalAndUnlistedFiles(): void
    {
        foreach (['../setup.php', '../../etc/passwd', '/etc/passwd', 'custom.png'] as $asset) {
            try {
                Paths::assetPath($asset);
                self::fail('Expected an invalid asset path to be rejected: ' . $asset);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
