<?php
declare(strict_types=1);

use GlpiPlugin\Responsivas\Service\ConfigService;
use PHPUnit\Framework\TestCase;

final class ConfigServiceTest extends TestCase
{
    protected function setUp(): void
    {
        Config::$values = [];
        Session::$messages = [];
        $GLOBALS['CFG_GLPI'] = ['root_doc' => '/glpi'];
    }

    private function invokePrivate(string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod(ConfigService::class, $method);
        return $reflection->invokeArgs(null, $arguments);
    }

    private function importJson(array $data, ?int $reportedSize = null): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'responsivas-config-test-');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary configuration fixture.');
        }

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            file_put_contents($path, $json);

            return $this->invokePrivate('importConfiguration', [[
                'error' => UPLOAD_ERR_OK,
                'size' => $reportedSize ?? strlen($json),
                'tmp_name' => $path,
            ]]);
        } finally {
            @unlink($path);
        }
    }

    public function testFontSizeNormalizationPreservesFractionalPoints(): void
    {
        self::assertSame(9.6, $this->invokePrivate('normalizeFontSize', ['9.56', 10]));
        self::assertSame(8.5, $this->invokePrivate('normalizeFontSize', [8.54, 10]));
        self::assertSame(10, $this->invokePrivate('normalizeFontSize', ['10', 9]));
    }

    public function testFontSizeNormalizationClampsToSupportedRange(): void
    {
        self::assertSame(6, $this->invokePrivate('normalizeFontSize', [0, 10]));
        self::assertSame(72, $this->invokePrivate('normalizeFontSize', [120, 10]));
        self::assertSame(6, $this->invokePrivate('normalizeFontSize', [-5, 10]));
    }

    public function testFontSizeNormalizationUsesSafeFallbackForInvalidValues(): void
    {
        self::assertSame(9.5, $this->invokePrivate('normalizeFontSize', ['not-a-number', 9.5]));
        self::assertSame(10, $this->invokePrivate('normalizeFontSize', [NAN, 'invalid']));
        self::assertSame(10, $this->invokePrivate('normalizeFontSize', [INF, 10]));
    }

    public function testConfigurationExportUsesAnExplicitAllowList(): void
    {
        $keys = $this->invokePrivate('exportableKeys');

        self::assertContains('company_name', $keys);
        self::assertContains('pc_inspection_title', $keys);
        self::assertContains('pho_return_footer_left_2', $keys);
        self::assertContains('pho_enable_return_form', $keys);

        self::assertNotContains('config_schema_version', $keys);
        self::assertNotContains('admin_email', $keys);
        self::assertNotContains('mail_password', $keys);
        self::assertNotContains('filesystem_path', $keys);
    }

    public function testConfigurationImportRejectsAnInvalidFormatWithoutChangingSettings(): void
    {
        Config::$values = ['plugin_responsivas' => ['company_name' => 'Original']];
        $response = $this->importJson([
            'format' => 'not-responsivas',
            'format_version' => 1,
            'configuration' => ['company_name' => 'Injected'],
        ]);

        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame('Original', Config::getConfigurationValues('plugin_responsivas')['company_name']);
    }

    public function testConfigurationImportEnforcesTheTwoMegabyteLimitBeforeReading(): void
    {
        Config::$values = ['plugin_responsivas' => ['company_name' => 'Original']];
        $response = $this->invokePrivate('importConfiguration', [[
            'error' => UPLOAD_ERR_OK,
            'size' => (2 * 1024 * 1024) + 1,
            'tmp_name' => '/path/that/must/not/be/read.json',
        ]]);

        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame('Original', Config::getConfigurationValues('plugin_responsivas')['company_name']);
    }

    public function testConfigurationImportNormalizesValuesAndIgnoresUnknownKeys(): void
    {
        $response = $this->importJson([
            'format' => 'responsivas-config',
            'format_version' => 1,
            'configuration' => [
                'company_name' => '  Example Company  ',
                'timezone' => 'UTC',
                'watermark_opacity' => 150,
                'pc_font_size' => '8.56',
                'unknown_admin_setting' => 'must not be imported',
            ],
            'logo' => [
                'mime' => 'image/jpeg',
                'data' => base64_encode('not-a-png'),
            ],
        ]);

        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        $values = Config::getConfigurationValues('plugin_responsivas');

        self::assertSame('Example Company', $values['company_name']);
        self::assertSame('UTC', $values['timezone']);
        self::assertSame(100, $values['watermark_opacity']);
        self::assertSame(8.6, $values['pc_font_size']);
        self::assertArrayNotHasKey('unknown_admin_setting', $values);
        self::assertNotEmpty(Session::$messages);
    }

}
