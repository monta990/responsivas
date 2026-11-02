<?php
declare(strict_types=1);

use GlpiPlugin\Responsivas\Exception\MissingEntityLocationException;
use GlpiPlugin\Responsivas\Pdf\PdfBuilder;
use PHPUnit\Framework\TestCase;

final class PdfBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        CommonDBTM::$records = [];
        Entity::$records = [];
        Session::$activeEntity = 1;
        Session::$allowedEntities = [1];
    }

    private function invokePrivate(string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod(PdfBuilder::class, $method);
        return $reflection->invokeArgs(null, $arguments);
    }

    public function testFilenameRemovesPathSeparatorsAndUnsafeCharacters(): void
    {
        $filename = PdfBuilder::makeFilename('computer', '../Jane Doe / IT');

        self::assertMatchesRegularExpression(
            '/^computer_Jane_Doe_IT_\d{4}-\d{2}-\d{2}\.pdf$/',
            $filename
        );
        self::assertStringNotContainsString('..', $filename);
        self::assertStringNotContainsString('/', $filename);
    }

    public function testPdfAddressEscapesHtmlAndPreservesLiteralNumberSign(): void
    {
        $result = $this->invokePrivate(
            'escapePdfAddress',
            ['Calle #57 <script>alert(1)</script>']
        );

        self::assertSame(
            'Calle <span>#</span>57 &lt;script&gt;alert(1)&lt;/script&gt;',
            $result
        );
    }

    public function testLoadViewableItemRejectsInvalidIdsAndMissingRecords(): void
    {
        $item = new CommonDBTM();

        self::assertNull($this->invokePrivate('loadViewableItem', [$item, 0]));
        self::assertNull($this->invokePrivate('loadViewableItem', [$item, 999]));
    }

    public function testLoadViewableItemRejectsItemsWithoutReadPermission(): void
    {
        CommonDBTM::$records = [
            10 => [
                'fields' => ['entities_id' => 1],
                'viewable' => false,
            ],
        ];

        self::assertNull(
            $this->invokePrivate('loadViewableItem', [new CommonDBTM(), 10])
        );
    }

    public function testLoadViewableItemRejectsItemsOutsideTheActiveEntityScope(): void
    {
        CommonDBTM::$records = [
            20 => [
                'fields' => ['entities_id' => 2],
                'viewable' => true,
            ],
        ];
        Session::$allowedEntities = [1];

        self::assertNull(
            $this->invokePrivate('loadViewableItem', [new CommonDBTM(), 20])
        );
    }

    public function testLoadViewableItemAcceptsAnAuthorizedItemInAnAccessibleEntity(): void
    {
        CommonDBTM::$records = [
            30 => [
                'fields' => ['entities_id' => 2, 'name' => 'Laptop'],
                'viewable' => true,
            ],
        ];
        Session::$allowedEntities = [1, 2];

        $item = $this->invokePrivate('loadViewableItem', [new CommonDBTM(), 30]);

        self::assertInstanceOf(CommonDBTM::class, $item);
        self::assertSame('Laptop', $item->fields['name']);
    }

    public function testFilterViewableRowsReturnsOnlyAuthorizedAssets(): void
    {
        CommonDBTM::$records = [
            41 => ['fields' => ['id' => 41, 'name' => 'Visible'], 'viewable' => true],
            42 => ['fields' => ['id' => 42, 'name' => 'Denied'], 'viewable' => false],
        ];

        $rows = $this->invokePrivate(
            'filterViewableRows',
            [CommonDBTM::class, [['id' => 41], ['id' => 42], ['id' => 0]]]
        );

        self::assertSame(
            [['id' => 41, 'name' => 'Visible']],
            $rows
        );
    }

    public function testLocationRequiresCityAndStateButCountryIsOptional(): void
    {
        Entity::$records = [
            1 => [
                'fields' => [
                    'town' => 'Hermosillo',
                    'state' => 'Sonora',
                    'country' => '',
                ],
                'viewable' => true,
            ],
        ];

        $data = $this->invokePrivate('getActiveEntityLocationData');

        self::assertSame(['Hermosillo', 'Sonora'], $data['parts']);
        self::assertSame([], $data['missing']);
        self::assertSame('Hermosillo, Sonora', $this->invokePrivate('getActiveEntityLocation'));
    }

    public function testLocationReportsMissingCityAndState(): void
    {
        Entity::$records = [
            1 => [
                'fields' => ['town' => '', 'state' => '', 'country' => 'Mexico'],
                'viewable' => true,
            ],
        ];

        $data = $this->invokePrivate('getActiveEntityLocationData');

        self::assertSame(['Mexico'], $data['parts']);
        self::assertSame(['City', 'State'], $data['missing']);
        $this->expectException(MissingEntityLocationException::class);
        PdfBuilder::validateActiveEntityLocation();
    }

    public function testMissingActiveEntityIsReported(): void
    {
        Entity::$records = [];

        $this->expectException(RuntimeException::class);
        $this->invokePrivate('getActiveEntityLocationData');
    }
}
