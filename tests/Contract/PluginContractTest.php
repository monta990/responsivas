<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PluginContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testPublishedInstallationFilesAndDirectoriesRemainPresent(): void
    {
        foreach (
            [
                'setup.php',
                'hook.php',
                'plugin.xml',
                'src',
                'templates',
                'locales',
                'assets',
            ] as $path
        ) {
            self::assertFileExists($this->root . '/' . $path, $path);
        }
    }

    public function testPluginVersionAndPhpMinimumAgreeAcrossPublishedMetadata(): void
    {
        $setup = (string)file_get_contents($this->root . '/setup.php');

        self::assertSame(
            1,
            preg_match(
                "/define\\('PLUGIN_RESPONSIVAS_VERSION',\\s*'([^']+)'\\)/",
                $setup,
                $versionMatch
            )
        );
        self::assertSame(
            1,
            preg_match("/'minphpversion'\\s*=>\\s*'([^']+)'/", $setup, $phpMatch)
        );

        $xml = simplexml_load_file($this->root . '/plugin.xml');
        self::assertInstanceOf(SimpleXMLElement::class, $xml);
        self::assertSame('responsivas', (string)$xml->key);
        self::assertSame($versionMatch[1], (string)$xml->versions->version[0]->num);
        self::assertSame('8.2', $phpMatch[1]);
        self::assertStringContainsString(
            "version_compare(PHP_VERSION, '8.2', '<')",
            $setup
        );
    }

    public function testAllRequiredPhpExtensionsAreCheckedBeforePluginActivation(): void
    {
        $setup = (string)file_get_contents($this->root . '/setup.php');

        foreach (['fileinfo', 'gd', 'intl', 'json'] as $extension) {
            self::assertStringContainsString("'" . $extension . "'", $setup);
        }
    }

    public function testGlpi11AndGlpi12CompatibilityDeclarationsArePreserved(): void
    {
        $setup = (string)file_get_contents($this->root . '/setup.php');
        $xml = simplexml_load_file($this->root . '/plugin.xml');

        self::assertInstanceOf(SimpleXMLElement::class, $xml);
        self::assertStringContainsString("PLUGIN_RESPONSIVAS_MIN_GLPI', '11.0'", $setup);
        self::assertStringContainsString("PLUGIN_RESPONSIVAS_MAX_GLPI', '12.99'", $setup);

        $xmlSource = (string)file_get_contents($this->root . '/plugin.xml');
        self::assertStringContainsString('<compatibility>~11.0</compatibility>', $xmlSource);
        self::assertStringContainsString('<compatibility>~12.0</compatibility>', $xmlSource);
    }

    public function testOriginalEnglishAndLocalizedInspectionAndReturnRoutesRemainAvailable(): void
    {
        $routes = [
            'src/Controller/InspectionController.php' => [
                '/inspection',
                '/inspeccion-visual',
                '/inspection-visuelle',
                '/sichtpruefung',
                '/ispezione-visiva',
            ],
            'src/Controller/ReturnController.php' => [
                '/return',
                '/devolucion',
                '/restitution',
                '/rueckgabe',
                '/restituzione',
            ],
        ];

        foreach ($routes as $file => $expectedRoutes) {
            $source = (string)file_get_contents($this->root . '/' . $file);

            foreach ($expectedRoutes as $route) {
                self::assertStringContainsString(
                    "#[Route('" . $route . "'",
                    $source,
                    $file . ' must preserve ' . $route
                );
            }
        }
    }

    public function testAllSixManualPreviewTypesRemainRegistered(): void
    {
        $source = (string)file_get_contents(
            $this->root . '/src/Controller/PreviewController.php'
        );

        foreach (
            [
                'manual_pc_inspection',
                'manual_pc_return',
                'manual_pri_inspection',
                'manual_pri_return',
                'manual_pho_inspection',
                'manual_pho_return',
            ] as $preview
        ) {
            self::assertStringContainsString("'" . $preview . "'", $source);
        }
    }
}
