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
            // query-only paths
            'SHOP' => null,
            'SHOP.SHOPITEM' => null,
            '*' => null,
            // format(file) — basic formats
            'csv(./path/to/file)' => null,
            'xml(./path/to/file)' => null,
            'json(./path/to/file)' => null,
            'jsonFile(./path/to/file)' => 'jsonfile(./path/to/file)',
            'neon(./path/to/file)' => null,
            'yaml(./path/to/file)' => null,
            'xls(./path/to/file)' => null,
            'ods(./path/to/file)' => null,
            // format(file).query
            'csv(./path/to/file).query.path' => null,
            'xml(feed.xml).SHOP.ITEM' => null,
            'json(data.json).data.users' => null,
            // CSV with positional params
            'csv(data.csv, "utf-8", ",")' => 'csv(data.csv)',
            'csv(data.csv, "utf-8", ";")' => null,
            'csv(data.csv, "windows-1250", ";")' => null,
            'csv(data.csv, "windows-1250")' => null,
            // CSV with named params
            'csv(data.csv, encoding: "windows-1250")' => 'csv(data.csv, "windows-1250")',
            'csv(data.csv, delimiter: ";")' => 'csv(data.csv, "utf-8", ";")',
            'csv(data.csv, encoding: "windows-1250", delimiter: ";")' => 'csv(data.csv, "windows-1250", ";")',
            // XML with params
            'xml(feed.xml, "utf-8").SHOP.ITEM' => 'xml(feed.xml).SHOP.ITEM',
            'xml(feed.xml, "windows-1250").SHOP.ITEM' => null,
            'xml(feed.xml, encoding: "windows-1250").SHOP.ITEM' => 'xml(feed.xml, "windows-1250").SHOP.ITEM',
            // case-insensitive format names — normalized to lowercase
            'CSV(data.csv)' => 'csv(data.csv)',
            'JSON(data.json).data.users' => 'json(data.json).data.users',
            'XML(feed.xml, "windows-1250").SHOP.ITEM' => 'xml(feed.xml, "windows-1250").SHOP.ITEM',
            'Csv(data.csv, encoding: "utf-8")' => 'csv(data.csv)',
        ];

        foreach ($testFileQueryPaths as $testFileQueryPath => $expectedFileQueryPath) {
            $fileQuery = new FileQuery($testFileQueryPath);
            $this->assertSame(
                $expectedFileQueryPath ?? $testFileQueryPath,
                (string) $fileQuery,
                sprintf('Round-trip failed for: %s', $testFileQueryPath)
            );
        }
    }

    public function testInvalidFileQuery(): void
    {
        $testFileQueryPaths = [
            // format too short (1 char)
            'c(./path/to/file).query.path' => 'Invalid query path',
            // unsupported formats
            'doc(./path/to/file).query.path' => 'Unsupported file format "doc"',
            'css(./path/to/file).query.path' => 'Unsupported file format "css"',
            'html(./path/to/file).query.path' => 'Unsupported file format "html"',
            // old bracket syntax is now invalid
            '[csv](./path/to/file)' => 'Invalid query path',
            '[xml](./path/to/file)' => 'Invalid query path',
        ];

        foreach ($testFileQueryPaths as $testFileQueryPath => $expectedErrorMessage) {
            try {
                new FileQuery($testFileQueryPath);
                $this->fail(sprintf('Expected exception for: %s', $testFileQueryPath));
            } catch (Exception\FileQueryException $e) {
                $this->assertStringStartsWith($expectedErrorMessage, $e->getMessage());
            } catch (Exception\InvalidFormatException $e) {
                $this->assertStringStartsWith('Unsupported file format', $e->getMessage());
            }
        }
    }

    public function testGetParam(): void
    {
        $fileQuery = new FileQuery('csv(data.csv, "windows-1250", ";")');

        $this->assertSame('windows-1250', $fileQuery->getParam('encoding'));
        $this->assertSame(';', $fileQuery->getParam('delimiter'));
        $this->assertNull($fileQuery->getParam('nonexistent'));
        $this->assertSame('default', $fileQuery->getParam('nonexistent', 'default'));
    }

    public function testParamsDefaults(): void
    {
        $csv = new FileQuery('csv(data.csv)');
        $this->assertSame('utf-8', $csv->getParam('encoding'));
        $this->assertSame(',', $csv->getParam('delimiter'));

        $xml = new FileQuery('xml(feed.xml)');
        $this->assertSame('utf-8', $xml->getParam('encoding'));

        $json = new FileQuery('json(data.json)');
        $this->assertSame([], $json->params);
    }

    public function testCannotMixPositionalAndNamed(): void
    {
        $this->expectException(Exception\InvalidFormatException::class);
        $this->expectExceptionMessage('Cannot mix positional and named parameters');

        new FileQuery('csv(data.csv, "utf-8", delimiter: ";")');
    }

    public function testInvalidEncoding(): void
    {
        $this->expectException(Exception\InvalidFormatException::class);
        $this->expectExceptionMessage('Unsupported encoding');

        new FileQuery('csv(data.csv, "invalid-encoding-xyz")');
    }

    public function testInvalidDelimiter(): void
    {
        $this->expectException(Exception\InvalidFormatException::class);
        $this->expectExceptionMessage('CSV delimiter must be a single character');

        new FileQuery('csv(data.csv, "utf-8", ";;")');
    }
}
