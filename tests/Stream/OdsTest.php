<?php

namespace Stream;

use FQL\Exception\FileNotFoundException;
use FQL\Stream\Ods;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\ODS\Writer;
use PHPUnit\Framework\TestCase;

class OdsTest extends TestCase
{
    private string $odsFile;

    protected function setUp(): void
    {
        $baseName = sys_get_temp_dir() . '/fiquela-ods-' . uniqid();
        $this->odsFile = $baseName . '.ods';

        $this->createSpreadsheet($this->odsFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->odsFile)) {
            unlink($this->odsFile);
        }
    }

    public function testOpen(): void
    {
        $ods = Ods::open($this->odsFile);

        $this->assertInstanceOf(Ods::class, $ods);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('File not found or not readable.');

        Ods::open('/path/to/file/not/existed.ods');
    }

    public function testReadFromSheetAndStartCell(): void
    {
        $stream = Ods::open($this->odsFile);
        $data = iterator_to_array($stream->getStream('Sheet1.G14'));

        $this->assertSame(
            [
                ['Name' => 'Product A', 'Price' => 10],
                ['Name' => 'Product B', 'Price' => 20],
            ],
            $data
        );
    }

    public function testReadBySheetIndex(): void
    {
        $stream = Ods::open($this->odsFile);
        $data = iterator_to_array($stream->getStream('2'));

        $this->assertSame(
            [
                ['Other' => 'Value1'],
            ],
            $data
        );
    }

    public function testReadBySheetIndexWithCell(): void
    {
        $stream = Ods::open($this->odsFile);
        $data = iterator_to_array($stream->getStream('1.G14'));

        $this->assertSame(
            [
                ['Name' => 'Product A', 'Price' => 10],
                ['Name' => 'Product B', 'Price' => 20],
            ],
            $data
        );
    }

    public function testReadDefaultSheet(): void
    {
        $stream = Ods::open($this->odsFile);
        $data = iterator_to_array($stream->getStream(null));

        $this->assertSame([], $data);
    }

    public function testSheetNotFound(): void
    {
        $stream = Ods::open($this->odsFile);

        $this->expectException(\FQL\Exception\UnableOpenFileException::class);
        $this->expectExceptionMessage('Sheet "NonExistent" not found.');
        iterator_to_array($stream->getStream('NonExistent'));
    }

    public function testStopAtEmptyRow(): void
    {
        $file = sys_get_temp_dir() . '/fiquela-ods-gap-' . uniqid() . '.ods';
        $writer = new Writer();
        $writer->openToFile($file);

        $writer->addRow(new Row([Cell::fromValue('Col1'), Cell::fromValue('Col2')]));
        $writer->addRow(new Row([Cell::fromValue('a'), Cell::fromValue('b')]));
        $writer->addRow(new Row([Cell::fromValue('c'), Cell::fromValue('d')]));
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue('')]));
        $writer->addRow(new Row([Cell::fromValue('e'), Cell::fromValue('f')]));

        $writer->close();

        try {
            $stream = Ods::open($file);
            $data = iterator_to_array($stream->getStream(null));

            $this->assertSame(
                [
                    ['Col1' => 'a', 'Col2' => 'b'],
                    ['Col1' => 'c', 'Col2' => 'd'],
                ],
                $data
            );
        } finally {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testProvideSource(): void
    {
        $stream = Ods::open($this->odsFile);
        $source = $stream->provideSource();

        $this->assertStringContainsString('[ods]', $source);
        $this->assertStringContainsString('.ods', $source);
    }

    private function createSpreadsheet(string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $sheet1 = $writer->getCurrentSheet();
        $sheet1->setName('Sheet1');

        // Rows 1-13: empty rows (padding)
        for ($r = 1; $r <= 13; $r++) {
            $writer->addRow(new Row([Cell::fromValue('')]));
        }

        // Row 14: 6 empty cells (A-F) + Name + Price in columns G and H
        $writer->addRow(new Row([
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue('Name'),
            Cell::fromValue('Price'),
        ]));

        // Row 15: 6 empty cells + Product A + 10
        $writer->addRow(new Row([
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue('Product A'),
            Cell::fromValue(10),
        ]));

        // Row 16: 6 empty cells + Product B + 20
        $writer->addRow(new Row([
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue('Product B'),
            Cell::fromValue(20),
        ]));

        // Sheet2: simple data
        $newSheet = $writer->addNewSheetAndMakeItCurrent();
        $newSheet->setName('Sheet2');
        $writer->addRow(new Row([Cell::fromValue('Other')]));
        $writer->addRow(new Row([Cell::fromValue('Value1')]));

        $writer->close();
    }
}
