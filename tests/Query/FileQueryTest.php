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
            'SHOP',
            'SHOP.SHOPITEM',
            '*',
            '(./path/to/file.xml)',
            '(./path/to/file.csv)',
            '(./path/to/file.yaml)',
            '(./path/to/file.neon)',
            '(./path/to/file).*',
            '(./path/to/file).SHOP',
            '(./path/to/file).SHOP.SHOPITEM',
            '[csv](./path/to/file)',
            '[xml](./path/to/file)',
            '[json](./path/to/file)',
            '[jsonFile](./path/to/file)',
            '[neon](./path/to/file)',
            '[yaml](./path/to/file)',
            '[csv](./path/to/file).query.path',
        ];

        foreach ($testFileQueryPaths as $testFileQueryPath) {
            $fileQuery = new FileQuery($testFileQueryPath);
            $this->assertSame($testFileQueryPath, (string) $fileQuery);
        }
    }

    public function testInvalidFileQuery(): void
    {
        $testFileQueryPaths = [
            '[csv]',
            '[csv].SHOP',
            '[csv].SHOP.SHOPITEM',
            '[cs](./path/to/file).query.path',
            '[xmls](./path/to/file).query.path',
            '[xlsx](./path/to/file).query.path',
            '[jsonfike](./path/to/file).query.path',
            '[doc](./path/to/file).query.path',
            '[css](./path/to/file).query.path',
            '[html](./path/to/file).query.path',
            '[xml]',
        ];
        foreach ($testFileQueryPaths as $testFileQueryPath) {
            try {
                new FileQuery($testFileQueryPath);
            } catch (Exception\FileQueryException $e) {
                $this->assertSame('Invalid query path', $e->getMessage());
            } catch (Exception\InvalidFormatException $e) {
                $this->assertStringStartsWith('Unsupported file format', $e->getMessage());
            }
        }
    }
}
