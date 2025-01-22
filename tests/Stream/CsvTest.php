<?php

namespace Stream;

use PHPUnit\Framework\TestCase;
use FQL\Exception\FileNotFoundException;
use FQL\Stream\Csv;

class CsvTest extends TestCase
{
    private string $utf8CsvFile;
    private string $w1250CsvFile;

    protected function setUp(): void
    {
        $this->utf8CsvFile = realpath(__DIR__ . '/../../examples/data/products-utf-8.csv');
        $this->w1250CsvFile = realpath(__DIR__ . '/../../examples/data/products-w-1250.csv');
    }

    public function testOpen(): void
    {
        $utf8Csv = Csv::open($this->utf8CsvFile);
        $w1250Csv = Csv::open($this->w1250CsvFile);
        $this->assertInstanceOf(Csv::class, $utf8Csv);
        $this->assertInstanceOf(Csv::class, $w1250Csv);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File not found or not readable.");

        Csv::open('/path/to/file/not/existed.csv');
    }

    public function testOpenWithOptions(): void
    {
        $utf8Csv = Csv::openWithDelimiter($this->utf8CsvFile);
        $this->assertEquals(',', $utf8Csv->getDelimiter());
        $this->assertNull($utf8Csv->getInputEncoding());
        $this->assertTrue($utf8Csv->isUseHeader());

        $w1250Csv = Csv::openWithDelimiter($this->w1250CsvFile, ';')
            ->setInputEncoding('windows-1250')
            ->useHeader(false);
        $this->assertEquals(';', $w1250Csv->getDelimiter());
        $this->assertEquals('windows-1250', $w1250Csv->getInputEncoding());
        $this->assertFalse($w1250Csv->isUseHeader());
    }
}
