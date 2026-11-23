<?php

namespace Query;

use FQL\Enum\Format;
use FQL\Exception\FileQueryException;
use FQL\Query\FileQuery;
use FQL\Query\Provider;
use PHPUnit\Framework\TestCase;

/**
 * Writing file queries back as FQL: every non-default parameter survives, and parts
 * that FQL cannot express raise a FileQueryException whose message names the part.
 */
class FileQueryWriterTest extends TestCase
{
    public function testParametersWithoutAPositionAreWrittenNamed(): void
    {
        $fileQuery = new FileQuery('csv(./a.csv, delimiter: ";")');

        $this->assertSame('csv(./a.csv, delimiter: ";", enclosure: "\'")', (string) $fileQuery->withParam('enclosure', "'"));
        $this->assertSame('csv(./a.csv, delimiter: ";", bom: "1")', (string) $fileQuery->withParam('bom', '1'));
        $this->assertSame(
            'log(./a.log, pattern: "%h %m %U")',
            (string) new FileQuery('log(./a.log, pattern: "%h %m %U")')
        );
    }

    public function testPositionalOutputIsKeptWhenEveryParameterHasAPosition(): void
    {
        $this->assertSame('csv(./a.csv, "utf-8", ";")', (string) new FileQuery('csv(./a.csv, delimiter: ";")'));
        $this->assertSame('xml(./a.xml, "windows-1250").ROOT', (string) new FileQuery('xml(./a.xml, encoding: "windows-1250").ROOT'));
    }

    public function testToFqlCanForceNamedParameters(): void
    {
        $fileQuery = new FileQuery('csv(./a.csv, "windows-1250", ";").items');

        $this->assertSame('csv(./a.csv, encoding: "windows-1250", delimiter: ";").items', $fileQuery->toFql(true));
        $this->assertSame('csv(./a.csv, "windows-1250", ";").items', $fileQuery->toFql());
        $this->assertSame('json(./a.json)', (new FileQuery('json(./a.json)'))->toFql(true));
    }

    public function testWrittenTextParsesBackToTheSameFileQuery(): void
    {
        $fileQuery = (new FileQuery('csv(./data/products.csv)'))
            ->withParams(['encoding' => 'windows-1250', 'delimiter' => ';', 'useHeader' => false, 'enclosure' => "'", 'bom' => true])
            ->withQuery('items');

        $again = new FileQuery((string) $fileQuery);

        $this->assertSame($fileQuery->params, $again->params);
        $this->assertSame('items', $again->query);
        $this->assertSame(
            ['encoding' => 'windows-1250', 'delimiter' => ';', 'useHeader' => '0', 'enclosure' => "'", 'bom' => '1'],
            $again->params
        );
    }

    public function testWithParamsReplacesTheParameterSet(): void
    {
        $fileQuery = new FileQuery('csv(./a.csv, "windows-1250", ";")');

        $this->assertSame('csv(./a.csv, delimiter: "|", bom: "1")', (string) $fileQuery->withParams(['delimiter' => '|', 'bom' => '1']));
        $this->assertSame('csv(./a.csv)', (string) $fileQuery->withParams([]));
    }

    public function testUnknownParametersAreNotWritten(): void
    {
        $fileQuery = new FileQuery('csv(./a.csv, delimiter: ";")');

        $this->assertSame('xml(./a.csv)', (string) $fileQuery->withFormat('xml'));
        $this->assertSame('json(./a.json)', (string) FileQuery::fromParts('json', './a.json', ['delimiter' => ';']));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function filePaths(): array
    {
        return [
            'plain path stays unquoted' => ['./data/a-b_c.csv', 'csv(./data/a-b_c.csv)'],
            'space stays unquoted' => ['./my data/a b.csv', 'csv(./my data/a b.csv)'],
            'parentheses' => ['./data (2026)/a(1).csv', 'csv("./data (2026)/a(1).csv")'],
            'brackets, plus and ampersand' => ['./a [v1] + b & c.csv', 'csv("./a [v1] + b & c.csv")'],
            'comma and colon' => ['./a, b: c.csv', 'csv("./a, b: c.csv")'],
            'windows path' => ['C:\\data\\a.csv', 'csv("C:\\data\\a.csv")'],
            'single quote' => ["./it's.csv", 'csv("./it\'s.csv")'],
            'double quote is written in single quotes' => ['./my "best" a.csv', 'csv(\'./my "best" a.csv\')'],
            'double quote and comma' => ['a.csv, "x"', 'csv(\'a.csv, "x"\')'],
            'leading space with a parenthesis' => [' a(1).csv', 'csv(" a(1).csv")'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('filePaths')]
    public function testFilePathsAreQuotedWhenNeededAndParseBack(string $file, string $expected): void
    {
        $fileQuery = (new FileQuery('csv(./a.csv)'))->withFile($file);

        $this->assertSame($file, $fileQuery->file);
        $this->assertSame($expected, (string) $fileQuery);
        $this->assertSame($file, (new FileQuery((string) $fileQuery))->file);
        $this->assertSame(substr($expected, 4, -1), FileQuery::pathToFql($file));
    }

    public function testQuotedFilePathKeepsParametersAndPathInFile(): void
    {
        $fileQuery = FileQuery::fromParts('csv', './my data (1).csv', ['delimiter' => ';', 'useHeader' => false], 'items');

        $this->assertSame('csv("./my data (1).csv", "utf-8", ";", "0").items', (string) $fileQuery);
        $this->assertSame('csv("./my data (1).csv", delimiter: ";", useHeader: "0").items', $fileQuery->toFql(true));

        $again = new FileQuery($fileQuery->toFql(true));
        $this->assertSame('./my data (1).csv', $again->file);
        $this->assertSame(';', $again->getParam('delimiter'));
        $this->assertSame('0', $again->getParam('useHeader'));
        $this->assertSame('items', $again->query);
    }

    public function testFromParts(): void
    {
        $this->assertSame(
            'xml(./feed.xml, "windows-1250").SHOP.SHOPITEM',
            (string) FileQuery::fromParts('xml', './feed.xml', ['encoding' => 'windows-1250'], 'SHOP.SHOPITEM')
        );
        $this->assertSame(
            'csv(./a.csv, delimiter: ";")',
            FileQuery::fromParts('csv', './a.csv', ['delimiter' => ';'], null, true)->toFql(true)
        );
        $this->assertSame('SHOP.ITEM', (string) FileQuery::fromParts(null, null, [], 'SHOP.ITEM'));
    }

    /**
     * @return array<string, array{\Closure(): mixed, string}>
     */
    public static function unwritable(): array
    {
        $csv = new FileQuery('csv(./a.csv)');
        return [
            'quote in a value' => [fn () => $csv->withParam('delimiter', '"'), 'contains a double quote'],
            'quote in a pattern' => [fn () => (new FileQuery('log(./a.log)'))->withParam('pattern', '"%r"'), 'contains a double quote'],
            'attribute in path in file' => [fn () => $csv->withQuery('SHOP.@id'), 'Path in file "SHOP.@id"'],
            'space in path in file' => [fn () => $csv->withQuery('my items'), 'Path in file "my items"'],
            'both quotes in file path' => [
                fn () => $csv->withFile('it\'s "best".csv'),
                'File path "it\'s \"best\".csv" cannot be written in a file query: it contains both a double and a single quote',
            ],
            'format without file' => [fn () => $csv->withFile(null), 'needs a file path'],
            'bad format name' => [fn () => FileQuery::fromParts('c', './a.csv'), 'Format "c"'],
        ];
    }

    /**
     * @param \Closure(): mixed $write
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unwritable')]
    public function testPartsThatCannotBeWrittenRaiseAClearError(\Closure $write, string $message): void
    {
        $this->expectException(FileQueryException::class);
        $this->expectExceptionMessage($message);
        $write();
    }

    public function testFormatParameterNames(): void
    {
        $this->assertSame(['encoding', 'delimiter', 'useHeader', 'enclosure', 'bom'], Format::CSV->getParamNames());
        $this->assertSame(['encoding', 'delimiter', 'useHeader'], Format::CSV->getPositionalParamNames());
        $this->assertSame(['format', 'pattern'], Format::LOG->getParamNames());
        $this->assertSame(['format'], Format::LOG->getPositionalParamNames());
        $this->assertSame([], Format::JSON_STREAM->getParamNames());
    }

    /**
     * The builder re-opens a FROM source through `(string) $fileQuery`; in 3.2.6 that
     * dropped the custom LOG pattern and the file was read with the default format.
     */
    public function testCustomLogPatternIsUsedWhenQueriedThroughFql(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fql-log-');
        $this->assertIsString($file);
        $log = $file . '.log';
        rename($file, $log);
        file_put_contents($log, "1.2.3.4 GET /x\n5.6.7.8 POST /y\n");
        try {
            $rows = iterator_to_array(
                Provider::fql(sprintf('SELECT host, requestMethod FROM log(%s, pattern: "%%h %%m %%U")', $log))
                    ->execute()
                    ->getIterator()
            );
        } finally {
            unlink($log);
        }

        $this->assertSame([['host' => '1.2.3.4', 'requestMethod' => 'GET'], ['host' => '5.6.7.8', 'requestMethod' => 'POST']], array_values($rows));
    }
}
