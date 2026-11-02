<?php
declare(strict_types=1);

use GlpiPlugin\Responsivas\Utils;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['glpilanguage' => 'en_US', 'glpi_tz' => 'UTC'];
        $GLOBALS['CFG_GLPI'] = ['language' => 'en_US'];
    }

    public function testEscapeEncodesHtmlSpecialCharacters(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
            Utils::escape('<script>alert("x")</script>')
        );
    }

    public function testApplyTemplateResolvesVariablesAndSupportedFormatting(): void
    {
        $result = Utils::applyTemplate(
            "Hello {nombre}\n**Strong** *Emphasis* __Underline__",
            ['{nombre}' => 'Ada']
        );

        self::assertStringContainsString('Hello Ada', $result);
        self::assertStringContainsString(
            '<span style="font-weight:bold">Strong</span>',
            $result
        );
        self::assertStringContainsString(
            '<span style="font-style:italic">Emphasis</span>',
            $result
        );
        self::assertStringContainsString(
            '<span style="text-decoration:underline">Underline</span>',
            $result
        );
    }

    public function testApplyTemplateRemovesInputHtmlBeforeRendering(): void
    {
        $result = Utils::applyTemplate('<script>alert("x")</script>', []);

        self::assertStringNotContainsString('<script', $result);
        self::assertStringContainsString('alert(&quot;x&quot;)', $result);
    }

    public function testRenderTemplateBuildsParagraphsAndOrderedLists(): void
    {
        $result = Utils::renderTemplate(
            "First paragraph\n\n1. First item\n2. Second item\n\nLast paragraph"
        );

        self::assertStringContainsString('First paragraph', $result);
        self::assertStringContainsString('<ol', $result);
        self::assertStringContainsString('<li>First item</li>', $result);
        self::assertStringContainsString('<li>Second item</li>', $result);
        self::assertStringContainsString('</ol>', $result);
        self::assertStringContainsString('Last paragraph', $result);
    }

    public function testDateToTextReturnsLocalizedTextForValidDates(): void
    {
        $result = Utils::dateToText('2024-01-02 12:00:00', 'UTC');

        self::assertNotSame('N/A', $result);
        self::assertNotSame('', $result);
    }

    public function testDateToTextUsesFallbackForMissingOrInvalidDates(): void
    {
        self::assertSame('N/A', Utils::dateToText(null, 'UTC'));
        self::assertSame('N/A', Utils::dateToText('0000-00-00', 'UTC'));
        self::assertSame('N/A', Utils::dateToText('not-a-date', 'UTC'));
        self::assertSame('N/A', Utils::dateToText('2024-01-02', 'Not/A_Timezone'));
    }
}
