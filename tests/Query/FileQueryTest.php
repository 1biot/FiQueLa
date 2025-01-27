<?php

namespace Query;

use FQL\Exception;
use FQL\Query\FileQuery;
use PHPUnit\Framework\TestCase;

class FileQueryTest extends TestCase
{
    public function testValidFileQuery(): void
    {
        $testFileQueryPaths = [
            'SHOP' => null,
            'SHOP.SHOPITEM' => null,
            '*' => null,
            '(./path/to/file.xml)' => null,
            '(./path/to/file.csv)' => null,
            '(./path/to/file.yaml)' => null,
            '(./path/to/file.neon)' => null,
            '(./path/to/file).*' => null,
            '(./path/to/file).SHOP' => null,
            '(./path/to/file).SHOP.SHOPITEM' => null,
            '[csv](./path/to/file)' => null,
            '[xml](./path/to/file)' => null,
            '[json](./path/to/file)' => '[jsonFile](./path/to/file)',
            '[jsonFile](./path/to/file)' => null,
            '[neon](./path/to/file)' => null,
            '[yaml](./path/to/file)' => null,
            '[csv](./path/to/file).query.path' => null,
        ];

        foreach ($testFileQueryPaths as $testFileQueryPath => $expectedFileQueryPath) {
            $fileQuery = new FileQuery($testFileQueryPath);
            $this->assertSame($expectedFileQueryPath ?? $testFileQueryPath, (string) $fileQuery);
        }
    }

    public function testInvalidFileQuery(): void
    {
        $testFileQueryPaths = [
            '[csv]' => 'Invalid query path "[csv]"',
            '[csv].SHOP' => 'Invalid query path "[csv].SHOP"',
            '[csv].SHOP.SHOPITEM' => 'Invalid query path "[csv].SHOP.SHOPITEM"',
            '[cs](./path/to/file).query.path' => 'Unsupported file format "cs"',
            '[xmls](./path/to/file).query.path' => 'Unsupported file format "xmls"',
            '[xlsx](./path/to/file).query.path' => 'Unsupported file format "xlsx"',
            '[jsonfike](./path/to/file).query.path' => 'Unsupported file format "jsonfike"',
            '[doc](./path/to/file).query.path' => 'Unsupported file format "doc"',
            '[css](./path/to/file).query.path' => 'Unsupported file format "css"',
            '[html](./path/to/file).query.path' => 'Unsupported file format "html"',
            '[xml]' => 'Invalid query path "[xml]"',
        ];
        foreach ($testFileQueryPaths as $testFileQueryPath => $expectedErrorMessage) {
            try {
                new FileQuery($testFileQueryPath);
            } catch (Exception\FileQueryException $e) {
                $this->assertSame($expectedErrorMessage, $e->getMessage());
            } catch (Exception\InvalidFormatException $e) {
                $this->assertStringStartsWith('Unsupported file format', $e->getMessage());
            }
        }
    }
}
