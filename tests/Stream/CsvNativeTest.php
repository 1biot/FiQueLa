<?php

namespace Stream;

use FQL\Query\FileQuery;
use FQL\Stream\Csv;
use FQL\Stream\Writers\CsvWriter;
use PHPUnit\Framework\TestCase;

/**
 * Covers behaviours of the native-fgetcsv / fputcsv CSV providers that
 * aren't reached by higher-level integration tests — BOM skipping, encoding
 * conversion paths, custom delimiters, header toggle, round-trip through
 * writer, edge cases with malformed / short rows.
 */
class CsvNativeTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = (string) tempnam(sys_get_temp_dir(), 'fql-csv-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmp)) {
            @unlink($this->tmp);
        }
    }

    public function testReadsSimpleCsvWithHeader(): void
    {
        file_put_contents($this->tmp, "id,name,price\n1,Alice,10\n2,Bob,20\n");
        $csv = Csv::openWithDelimiter($this->tmp);
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        $this->assertCount(2, $rows);
        // All values arrive as raw strings — typing is now lazy.
        $this->assertSame(['id' => '1', 'name' => 'Alice', 'price' => '10'], $rows[0]);
        $this->assertSame(['id' => '2', 'name' => 'Bob', 'price' => '20'], $rows[1]);
    }

    public function testCustomDelimiter(): void
    {
        file_put_contents($this->tmp, "id;name;price\n1;Alice;10\n");
        $csv = Csv::openWithDelimiter($this->tmp, ';');
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        $this->assertSame(['id' => '1', 'name' => 'Alice', 'price' => '10'], $rows[0]);
    }

    public function testWithoutHeaderYieldsNumericRows(): void
    {
        file_put_contents($this->tmp, "1,Alice\n2,Bob\n");
        $csv = Csv::openWithDelimiter($this->tmp)->useHeader(false);
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        $this->assertSame([['1', 'Alice'], ['2', 'Bob']], $rows);
    }

    public function testSkipsUtf8Bom(): void
    {
        // UTF-8 BOM + header + one row.
        file_put_contents($this->tmp, "\xEF\xBB\xBFid,name\n1,Alice\n");
        $csv = Csv::openWithDelimiter($this->tmp);
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        // First header key must be "id", not "\xEF\xBB\xBFid".
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertSame('1', $rows[0]['id']);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testWindows1250EncodingStreamFilter(): void
    {
        // Encode a Czech header + row in windows-1250 and verify the provider
        // transcodes it on the fly via the stream filter.
        $utf8 = "jméno,město\nPříliš,Plzeň\n";
        $cp1250 = iconv('UTF-8', 'windows-1250', $utf8);
        $this->assertNotFalse($cp1250);
        file_put_contents($this->tmp, $cp1250);

        $csv = Csv::openWithDelimiter($this->tmp)->setInputEncoding('windows-1250');
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        $this->assertArrayHasKey('jméno', $rows[0]);
        $this->assertSame('Příliš', $rows[0]['jméno']);
        $this->assertSame('Plzeň', $rows[0]['město']);
    }

    public function testShortRowsPaddedToHeaderLength(): void
    {
        file_put_contents($this->tmp, "a,b,c\n1,2\n");
        $csv = Csv::openWithDelimiter($this->tmp);
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        $this->assertSame(['a' => '1', 'b' => '2', 'c' => null], $rows[0]);
    }

    public function testLongRowsTruncatedToHeaderLength(): void
    {
        file_put_contents($this->tmp, "a,b\n1,2,3,4\n");
        $csv = Csv::openWithDelimiter($this->tmp);
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        $this->assertSame(['a' => '1', 'b' => '2'], $rows[0]);
    }

    public function testWriterProducesRoundTrippableCsv(): void
    {
        $fq = new FileQuery(sprintf('csv(%s)', $this->tmp));
        $writer = new CsvWriter($fq);
        $writer->write(['id' => 1, 'name' => 'Alice', 'note' => 'hello']);
        $writer->write(['id' => 2, 'name' => 'Bob', 'note' => null]);
        $writer->close();

        $content = (string) file_get_contents($this->tmp);
        $this->assertStringStartsWith("id,name,note\n", $content);
        $this->assertStringContainsString("1,Alice,hello", $content);

        // Read back and check raw string shape.
        $csv = Csv::openWithDelimiter($this->tmp);
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        $this->assertSame(['id' => '1', 'name' => 'Alice', 'note' => 'hello'], $rows[0]);
        $this->assertSame(['id' => '2', 'name' => 'Bob', 'note' => ''], $rows[1]);
    }

    public function testWriterEncodesToWindows1250(): void
    {
        $fq = new FileQuery(sprintf('csv(%s, "windows-1250", ";")', $this->tmp));
        $writer = new CsvWriter($fq);
        $writer->write(['name' => 'Příliš', 'value' => 123]);
        $writer->write(['name' => 'žluťoučký', 'value' => 456]);
        $writer->close();

        $hex = bin2hex((string) file_get_contents($this->tmp));
        // CP1250 bytes for "Pří" = 50 f8 ed, for "žlu" = 9e 6c 75
        $this->assertStringContainsString('50f8ed', $hex);
        $this->assertStringContainsString('9e6c75', $hex);

        // No UTF-8 BOM by default.
        $this->assertStringStartsNotWith("\xEF\xBB\xBF", (string) file_get_contents($this->tmp));
    }

    public function testWriterOptInBom(): void
    {
        $fq = new FileQuery(sprintf('csv(%s, "utf-8", ",", "1", "1")', $this->tmp));
        // positional: encoding, delimiter, useHeader, bom — but Format::normalizeParams
        // doesn't know "bom" positional; use named instead.
        $fq = new FileQuery(sprintf('csv(%s, bom: "1")', $this->tmp));
        $writer = new CsvWriter($fq);
        $writer->write(['a' => 'x']);
        $writer->close();
        $content = (string) file_get_contents($this->tmp);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }
}
