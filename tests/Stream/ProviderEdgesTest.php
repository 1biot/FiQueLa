<?php

namespace Stream;

use FQL\Exception\FileNotFoundException;
use FQL\Exception\UnableOpenFileException;
use FQL\Stream\Csv;
use FQL\Stream\Json;
use FQL\Stream\JsonStream;
use FQL\Stream\NDJson;
use FQL\Stream\Ods;
use FQL\Stream\Provider as StreamProvider;
use FQL\Stream\Xls;
use FQL\Stream\Xml;
use PHPUnit\Framework\TestCase;

/**
 * Sweeps up the edge / error paths on stream providers that aren't reached
 * by the format-specific integration tests. Focus:
 *  - missing / unreadable source files should raise the right exceptions
 *  - `StreamProvider::fromFile()` format auto-detection
 *  - stdin / empty / trivial content handling where applicable
 */
class ProviderEdgesTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/fql-stream-edges-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    // --- Provider::fromFile autodetection ---------------------------------

    public function testProviderAutodetectsCsvByExtension(): void
    {
        $path = $this->tmpDir . '/test.csv';
        file_put_contents($path, "id,name\n1,Alice\n");
        $stream = StreamProvider::fromFile($path);
        $this->assertInstanceOf(Csv::class, $stream);
    }

    public function testProviderAutodetectsJsonByExtension(): void
    {
        $path = $this->tmpDir . '/test.json';
        file_put_contents($path, '[{"id":1}]');
        $stream = StreamProvider::fromFile($path);
        // Default JSON mapping is the streaming reader; verify it's one of
        // the known JSON-like stream classes.
        $this->assertTrue(
            $stream instanceof Json
            || $stream instanceof JsonStream
            || $stream instanceof NDJson
        );
    }

    public function testProviderAutodetectsXmlByExtension(): void
    {
        $path = $this->tmpDir . '/test.xml';
        file_put_contents($path, '<?xml version="1.0"?><root><item>a</item></root>');
        $stream = StreamProvider::fromFile($path);
        $this->assertInstanceOf(Xml::class, $stream);
    }

    public function testProviderThrowsOnMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        StreamProvider::fromFile($this->tmpDir . '/nonexistent.csv');
    }

    // --- CSV ---------------------------------------------------------------

    public function testCsvOpenThrowsOnMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        Csv::open($this->tmpDir . '/does-not-exist.csv');
    }

    public function testCsvUnsupportedEncodingBubblesUp(): void
    {
        $path = $this->tmpDir . '/bad.csv';
        file_put_contents($path, "id,name\n1,Alice\n");
        $this->expectException(UnableOpenFileException::class);
        $csv = Csv::openWithDelimiter($path)->setInputEncoding('NOT-A-REAL-ENCODING-ZZZ');
        iterator_to_array($csv->getStreamGenerator(null), false);
    }

    public function testCsvWithoutHeaderToggle(): void
    {
        $path = $this->tmpDir . '/nohead.csv';
        file_put_contents($path, "a,b\nc,d\n");
        $csv = Csv::openWithDelimiter($path)->useHeader(false);
        $rows = iterator_to_array($csv->getStreamGenerator(null), false);
        $this->assertCount(2, $rows);
        $this->assertSame(['a', 'b'], $rows[0]);
    }

    public function testCsvProvideSourceReflectsOptions(): void
    {
        $path = $this->tmpDir . '/src.csv';
        file_put_contents($path, 'x,y');
        $csv = Csv::openWithDelimiter($path, ';')->setInputEncoding('windows-1250');
        $src = $csv->provideSource();
        $this->assertStringContainsString('windows-1250', $src);
        $this->assertStringContainsString(';', $src);
    }

    public function testCsvProvideSourceWithoutHeader(): void
    {
        $path = $this->tmpDir . '/src2.csv';
        file_put_contents($path, 'x,y');
        $csv = Csv::openWithDelimiter($path)->useHeader(false);
        $src = $csv->provideSource();
        $this->assertStringContainsString('"0"', $src);
    }

    // --- JSON / NDJson / JsonStream ---------------------------------------

    public function testJsonString(): void
    {
        $json = Json::string('[{"id":1},{"id":2}]');
        $rows = iterator_to_array($json->getStreamGenerator(null), false);
        $this->assertCount(2, $rows);
    }

    public function testJsonOpenMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        Json::open($this->tmpDir . '/missing.json');
    }

    public function testNDJsonOpenAndIterate(): void
    {
        $path = $this->tmpDir . '/items.ndjson';
        file_put_contents($path, "{\"id\":1}\n{\"id\":2}\n");
        $stream = NDJson::open($path);
        $rows = iterator_to_array($stream->getStreamGenerator(null), false);
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['id']);
    }

    public function testJsonStreamOpen(): void
    {
        $path = $this->tmpDir . '/stream.json';
        file_put_contents($path, '[{"id":1},{"id":2}]');
        $stream = JsonStream::open($path);
        $rows = iterator_to_array($stream->getStreamGenerator(null), false);
        $this->assertCount(2, $rows);
    }

    // --- Xls / Ods --------------------------------------------------------

    public function testXlsOpenMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        Xls::open($this->tmpDir . '/missing.xlsx');
    }

    public function testOdsOpenMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        Ods::open($this->tmpDir . '/missing.ods');
    }

    public function testXmlOpenMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        Xml::open($this->tmpDir . '/missing.xml');
    }
}
