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
            // CSV with useHeader param
            'csv(data.csv, "utf-8", ",", "0")' => 'csv(data.csv, "utf-8", ",", "0")',
            'csv(data.csv, "utf-8", ",", "1")' => 'csv(data.csv)',
            'csv(data.csv, useHeader: "0")' => 'csv(data.csv, "utf-8", ",", "0")',
            'csv(data.csv, encoding: "windows-1250", useHeader: "0")' => 'csv(data.csv, "windows-1250", ",", "0")',
            // case-insensitive format names — normalized to lowercase
            'CSV(data.csv)' => 'csv(data.csv)',
            'JSON(data.json).data.users' => 'json(data.json).data.users',
            'XML(feed.xml, "windows-1250").SHOP.ITEM' => 'xml(feed.xml, "windows-1250").SHOP.ITEM',
            'Csv(data.csv, encoding: "utf-8")' => 'csv(data.csv)',
            // quoted file paths — quotes are kept only when the path needs them
            'csv("./data.csv")' => 'csv(./data.csv)',
            'csv("./my data/file.csv")' => 'csv(./my data/file.csv)',
            'csv("./my data (2026)/file [v1].csv")' => null,
            'csv( "./data (1).csv" )' => 'csv("./data (1).csv")',
            'csv("./data (1).csv", "windows-1250", ";").items' => null,
            'csv("./data (1).csv", encoding: "windows-1250", delimiter: ";").*' => 'csv("./data (1).csv", "windows-1250", ";").*',
            'csv("./data (1).csv",delimiter:";")' => 'csv("./data (1).csv", "utf-8", ";")',
            'xml("C:\\feeds\\feed (2).xml", encoding: "windows-1250").SHOP.ITEM' => 'xml("C:\\feeds\\feed (2).xml", "windows-1250").SHOP.ITEM',
            'json("./a, b: c.json").data.users' => null,
            'jsonFile("./žluťoučký kůň (1).json").*' => 'jsonfile("./žluťoučký kůň (1).json").*',
            'log("./logs/access (old).log", "apache_common").*' => null,
            'dir("./my files (2)")' => null,
            // single-quoted file paths — written with double quotes unless the path contains `"`
            "csv('./data.csv')" => 'csv(./data.csv)',
            "csv('./data (1).csv')" => 'csv("./data (1).csv")',
            "csv( './data (1).csv' , \"windows-1250\", \";\").items" => 'csv("./data (1).csv", "windows-1250", ";").items',
            "csv('./data (1).csv', encoding: \"windows-1250\", delimiter: \";\").*" => 'csv("./data (1).csv", "windows-1250", ";").*',
            "csv('./my \"best\" data.csv', delimiter: \";\")" => "csv('./my \"best\" data.csv', \"utf-8\", \";\")",
            "csv(\"./it's (1).csv\")" => null,
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
            // parentheses in a path need quotes
            'csv(./data (1).csv)' => 'Invalid query path',
            // quoted file paths
            'csv("")' => 'Invalid query path',
            'csv("./a"b.csv")' => 'Invalid query path',
            'csv("./unterminated.csv)' => 'Invalid query path',
            'csv("./a.csv" "utf-8")' => 'Invalid query path',
            "csv('')" => 'Invalid query path',
            "csv('./a'b.csv')" => 'Invalid query path',
            // the quotes must match
            "csv('./a (1).csv\")" => 'Invalid query path',
            "csv(\"./a (1).csv')" => 'Invalid query path',
            // backticks are not file path quotes
            'csv(`./a.csv`)' => 'Invalid query path',
            'csv(`./my data (1).csv`, delimiter: ";")' => 'Invalid query path',
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

    public function testQuotedFilePathIsReadBetweenTheQuotes(): void
    {
        $positional = new FileQuery('csv("./my data (2026)/file [v1].csv", "windows-1250", ";", "0").items');
        $this->assertSame('csv', $positional->format);
        $this->assertSame('./my data (2026)/file [v1].csv', $positional->file);
        $this->assertSame('windows-1250', $positional->getParam('encoding'));
        $this->assertSame(';', $positional->getParam('delimiter'));
        $this->assertSame('0', $positional->getParam('useHeader'));
        $this->assertSame('items', $positional->query);

        $named = new FileQuery('csv( "./my data (2026)/file [v1].csv" , delimiter: ";", encoding: "windows-1250").items');
        $this->assertSame('./my data (2026)/file [v1].csv', $named->file);
        $this->assertSame($positional->getParam('encoding'), $named->getParam('encoding'));
        $this->assertSame($positional->getParam('delimiter'), $named->getParam('delimiter'));
        $this->assertSame('items', $named->query);

        $single = new FileQuery("csv('./my data (2026)/file [v1].csv', delimiter: \";\", encoding: \"windows-1250\").items");
        $this->assertSame('./my data (2026)/file [v1].csv', $single->file);
        $this->assertSame($positional->getParam('encoding'), $single->getParam('encoding'));
        $this->assertSame($positional->getParam('delimiter'), $single->getParam('delimiter'));
        $this->assertSame('items', $single->query);

        // spaces inside the quotes are part of the path, the other quote is a plain character
        $this->assertSame(' a (1).csv ', (new FileQuery('csv(" a (1).csv ")'))->file);
        $this->assertSame('./my "best" a.csv', (new FileQuery("csv('./my \"best\" a.csv')"))->file);
        $this->assertSame("./it's a.csv", (new FileQuery("csv(\"./it's a.csv\")"))->file);
    }

    public function testQuotedFilePathCannotMixPositionalAndNamed(): void
    {
        $this->expectException(Exception\InvalidFormatException::class);
        $this->expectExceptionMessage('Cannot mix positional and named parameters');
        new FileQuery('csv("./data (1).csv", "utf-8", delimiter: ";")');
    }

    public function testGetParam(): void
    {
        $fileQuery = new FileQuery('csv(data.csv, "windows-1250", ";")');

        $this->assertSame('windows-1250', $fileQuery->getParam('encoding'));
        $this->assertSame(';', $fileQuery->getParam('delimiter'));
        $this->assertSame('1', $fileQuery->getParam('useHeader'));
        $this->assertNull($fileQuery->getParam('nonexistent'));
        $this->assertSame('default', $fileQuery->getParam('nonexistent', 'default'));
    }

    public function testParamsDefaults(): void
    {
        $csv = new FileQuery('csv(data.csv)');
        $this->assertSame('utf-8', $csv->getParam('encoding'));
        $this->assertSame(',', $csv->getParam('delimiter'));
        $this->assertSame('1', $csv->getParam('useHeader'));

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

    public function testInvalidUseHeader(): void
    {
        $this->expectException(Exception\InvalidFormatException::class);
        $this->expectExceptionMessage('CSV useHeader must be "0" or "1"');

        new FileQuery('csv(data.csv, "utf-8", ",", "yes")');
    }
}
