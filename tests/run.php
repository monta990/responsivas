<?php
declare(strict_types=1);

use GlpiPlugin\Responsivas\Paths;
use GlpiPlugin\Responsivas\Pdf\PdfBuilder;
use GlpiPlugin\Responsivas\Service\ConfigService;
use GlpiPlugin\Responsivas\Utils;

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true)
        );
    }
};

$assertTrue = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertFalse = static function (bool $condition, string $message): void {
    if ($condition) {
        throw new RuntimeException($message);
    }
};

$assertThrows = static function (callable $callback, string $class, string $message): void {
    try {
        $callback();
    } catch (Throwable $error) {
        if ($error instanceof $class) {
            return;
        }
        throw new RuntimeException($message . ' Unexpected exception: ' . $error::class, 0, $error);
    }

    throw new RuntimeException($message . ' No exception was thrown.');
};

$private = static function (string $class, string $method, array $arguments = []): mixed {
    return (new ReflectionMethod($class, $method))->invokeArgs(null, $arguments);
};

$tests = [
    'PDF filenames remove path and control characters' => static function () use ($assertTrue): void {
        $name = PdfBuilder::makeFilename('computer', '../Jane Doe / IT');
        $assertTrue(
            preg_match('/^computer_Jane_Doe_IT_\d{4}-\d{2}-\d{2}\.pdf$/', $name) === 1,
            'Generated filename must be a safe PDF basename.'
        );
    },

    'PDF address escapes markup and preserves the literal number sign' => static function () use ($assertSame, $private): void {
        $result = $private(
            PdfBuilder::class,
            'escapePdfAddress',
            ['Calle #57 <script>alert(1)</script>']
        );
        $assertSame(
            'Calle <span>#</span>57 &lt;script&gt;alert(1)&lt;/script&gt;',
            $result,
            'Address must be safe for TCPDF HTML rendering.'
        );
    },

    'PDF object authorization rejects missing records and invalid IDs' => static function () use ($assertSame, $private): void {
        CommonDBTM::$records = [];
        Session::$allowedEntities = [1];
        $assertSame(null, $private(PdfBuilder::class, 'loadViewableItem', [new CommonDBTM(), 0]), 'Zero ID must be rejected.');
        $assertSame(null, $private(PdfBuilder::class, 'loadViewableItem', [new CommonDBTM(), 123]), 'Missing asset must be rejected.');
    },

    'PDF object authorization rejects records without read permission' => static function () use ($assertSame, $private): void {
        CommonDBTM::$records = [
            10 => ['fields' => ['entities_id' => 1], 'viewable' => false],
        ];
        $assertSame(null, $private(PdfBuilder::class, 'loadViewableItem', [new CommonDBTM(), 10]), 'Non-viewable asset must be rejected.');
    },

    'PDF object authorization enforces GLPI entity access' => static function () use ($assertSame, $assertTrue, $private): void {
        CommonDBTM::$records = [
            20 => ['fields' => ['entities_id' => 2, 'name' => 'Laptop'], 'viewable' => true],
        ];
        Session::$allowedEntities = [1];
        $assertSame(null, $private(PdfBuilder::class, 'loadViewableItem', [new CommonDBTM(), 20]), 'Cross-entity asset must be rejected.');

        Session::$allowedEntities = [1, 2];
        $item = $private(PdfBuilder::class, 'loadViewableItem', [new CommonDBTM(), 20]);
        $assertTrue($item instanceof CommonDBTM, 'Viewable asset in an accessible entity must be accepted.');
    },

    'PDF batch filtering excludes unauthorized assets' => static function () use ($assertSame, $private): void {
        CommonDBTM::$records = [
            31 => ['fields' => ['id' => 31, 'name' => 'Visible'], 'viewable' => true],
            32 => ['fields' => ['id' => 32, 'name' => 'Denied'], 'viewable' => false],
        ];
        Session::$allowedEntities = [1];
        $rows = $private(
            PdfBuilder::class,
            'filterViewableRows',
            [CommonDBTM::class, [['id' => 31], ['id' => 32], ['id' => 999]]]
        );
        $assertSame([['id' => 31, 'name' => 'Visible']], $rows, 'Only authorized rows should enter generated documents.');
    },

    'PDF entity location requires city and state while country remains optional' => static function () use ($assertSame, $assertThrows, $private): void {
        Entity::$records = [
            1 => ['fields' => ['town' => 'Hermosillo', 'state' => 'Sonora', 'country' => ''], 'viewable' => true],
        ];
        Session::$activeEntity = 1;
        $data = $private(PdfBuilder::class, 'getActiveEntityLocationData');
        $assertSame(['Hermosillo', 'Sonora'], $data['parts'], 'Configured city and state must be retained.');
        $assertSame([], $data['missing'], 'Country must remain optional.');
        $assertSame('Hermosillo, Sonora', $private(PdfBuilder::class, 'getActiveEntityLocation'), 'Location string should be stable.');

        Entity::$records[1]['fields']['town'] = '';
        $assertSame(['City'], $private(PdfBuilder::class, 'getActiveEntityLocationData')['missing'], 'Missing city must be reported.');
        $assertThrows(
            static fn() => PdfBuilder::validateActiveEntityLocation(),
            \GlpiPlugin\Responsivas\Exception\MissingEntityLocationException::class,
            'Incomplete entity location must prevent PDF generation.'
        );
    },

    'PDF builder reports a missing active entity' => static function () use ($assertThrows, $private): void {
        Entity::$records = [];
        $assertThrows(
            static fn() => $private(PdfBuilder::class, 'getActiveEntityLocationData'),
            RuntimeException::class,
            'An unknown active entity must be rejected.'
        );
    },

    'PDF configuration font sizes are normalized and clamped' => static function () use ($assertSame, $private): void {
        $assertSame(9.6, $private(ConfigService::class, 'normalizeFontSize', ['9.56', 10]), 'Fractional point precision must be retained.');
        $assertSame(6, $private(ConfigService::class, 'normalizeFontSize', [0, 10]), 'Font size lower bound must be 6 pt.');
        $assertSame(72, $private(ConfigService::class, 'normalizeFontSize', [120, 10]), 'Font size upper bound must be 72 pt.');
        $assertSame(9.5, $private(ConfigService::class, 'normalizeFontSize', ['invalid', 9.5]), 'Invalid values must use a safe fallback.');
    },

    'Configuration export is restricted to an explicit allow-list' => static function () use ($assertTrue, $assertFalse, $private): void {
        $keys = $private(ConfigService::class, 'exportableKeys');
        $assertTrue(in_array('pc_inspection_title', $keys, true), 'Manual inspection configuration must be exportable.');
        $assertTrue(in_array('pho_return_footer_left_2', $keys, true), 'Manual return footer configuration must be exportable.');
        $assertFalse(in_array('config_schema_version', $keys, true), 'Internal schema state must not be exportable.');
        $assertFalse(in_array('mail_password', $keys, true), 'Secrets must never enter the export allow-list.');
    },

    'Localized manual routes preserve every published language slug' => static function () use ($assertSame): void {
        $expected = [
            'en_GB' => ['inspection', 'return'],
            'es_MX' => ['inspeccion-visual', 'devolucion'],
            'fr_FR' => ['inspection-visuelle', 'restitution'],
            'de_DE' => ['sichtpruefung', 'rueckgabe'],
            'it_IT' => ['ispezione-visiva', 'restituzione'],
        ];
        foreach ($expected as $language => [$inspection, $return]) {
            $_SESSION['glpilanguage'] = $language;
            $assertSame($inspection, Paths::manualFormSlug('inspection'), 'Inspection slug changed for ' . $language);
            $assertSame($return, Paths::manualFormSlug('return'), 'Return slug changed for ' . $language);
        }
    },

    'Manual route rejects unrecognized forms and asset traversal' => static function () use ($assertThrows): void {
        $assertThrows(
            static fn() => Paths::manualFormSlug('../inspection'),
            InvalidArgumentException::class,
            'Invalid manual route must be rejected.'
        );
        $assertThrows(
            static fn() => Paths::assetPath('../../etc/passwd'),
            InvalidArgumentException::class,
            'Path traversal must be rejected.'
        );
    },

    'Template rendering preserves paragraphs and ordered clauses' => static function () use ($assertTrue): void {
        $html = Utils::renderTemplate("Clause one\n\n1. First\n2. Second\n\nFinal paragraph");
        $assertTrue(str_contains($html, '<ol'), 'Numbered clauses must become an ordered list.');
        $assertTrue(str_contains($html, '<li>First</li>'), 'First numbered clause must be rendered.');
        $assertTrue(str_contains($html, '<li>Second</li>'), 'Second numbered clause must be rendered.');
        $assertTrue(str_contains($html, 'Final paragraph'), 'The final paragraph must remain in the document.');
    },

        'Template rendering can opt out of keeping prose paragraphs together' => static function () use ($assertTrue, $assertFalse): void {
        $template = "Opening paragraph with editable text.\n\n1. First clause\n2. Second clause\n\nFinal witness paragraph";
        $legacyHtml = Utils::renderTemplate($template);
        $flowingHtml = Utils::renderTemplate($template, false);

        $assertTrue(str_contains($legacyHtml, '<table nobr="true"'), 'The default renderer must preserve the historical indivisible paragraph layout.');
        $assertFalse(str_contains($flowingHtml, '<table nobr="true"'), 'Flowing mode must not wrap prose paragraphs in indivisible tables.');
        $assertTrue(str_contains($flowingHtml, '<p style="text-align:justify;line-height:1.2;'), 'Flowing mode must render prose as normal paragraphs.');
        $assertTrue(str_contains($flowingHtml, '<li>First clause</li>'), 'Flowing mode must preserve ordered clauses.');
        $assertTrue(str_contains($flowingHtml, '<li>Second clause</li>'), 'Flowing mode must preserve all ordered clauses.');
    },

    'Phone contract prose renderer does not bundle signatures into an HTML no-break table' => static function () use ($assertTrue, $assertFalse, $private): void {
        $html = $private(
            PdfBuilder::class,
            'renderPhoPage',
            ['CONTRATO DE COMODATO', 'Opening paragraph', '<ol><li>Clause</li></ol>', 'Witness paragraph']
        );

        $assertTrue(str_contains($html, 'Opening paragraph'), 'Phone contract opening paragraph must be rendered.');
        $assertTrue(str_contains($html, '<li>Clause</li>'), 'Phone contract clauses must be rendered.');
        $assertTrue(str_contains($html, 'Witness paragraph'), 'Phone contract witness paragraph must be rendered.');
        $assertFalse(str_contains($html, 'nobr="true"'), 'Phone contract prose must be allowed to flow naturally.');
        $assertFalse(str_contains($html, 'BORROWER'), 'The HTML prose renderer must not own the signature grid.');
    },

'Template formatting escapes markup before adding supported emphasis' => static function () use ($assertTrue, $assertFalse): void {
        $html = Utils::applyTemplate('<script>alert(1)</script> **Bold**', []);
        $assertFalse(str_contains($html, '<script>'), 'User template markup must not inject a script element.');
        $assertTrue(str_contains($html, 'alert(1)'), 'Text between unsafe tags must remain readable after tags are stripped.');
        $assertTrue(str_contains($html, '<span style="font-weight:bold">Bold</span>'), 'Supported bold formatting must remain available.');
    },

    'Date formatting has a safe fallback for invalid input' => static function () use ($assertSame, $assertTrue): void {
        $assertSame('N/A', Utils::dateToText(null, 'UTC'), 'Missing date should use the documented fallback.');
        $assertSame('N/A', Utils::dateToText('not-a-date', 'UTC'), 'Invalid date should use the documented fallback.');
        $valid = Utils::dateToText('2024-01-02 12:00:00', 'UTC');
        $assertTrue($valid !== '' && $valid !== 'N/A', 'A valid date should be localized.');
    },
];

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
        fwrite(STDOUT, "[PASS] {$name}\n");
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, "[FAIL] {$name}: {$error->getMessage()}\n");
    }
}

fwrite(
    $failed === 0 ? STDOUT : STDERR,
    sprintf("\n%d/%d CLI business tests passed.\n", $passed, count($tests))
);

exit($failed === 0 ? 0 : 1);
