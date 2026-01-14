<?php

namespace Stream;

use FQL\Exception\FileNotFoundException;
use FQL\Stream\Xls;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls as XlsWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PHPUnit\Framework\TestCase;

class XlsTest extends TestCase
{
    private string $xlsxFile;
    private string $xlsFile;

    protected function setUp(): void
    {
        $baseName = sys_get_temp_dir() . '/fiquela-xls-' . uniqid();
        $this->xlsxFile = $baseName . '.xlsx';
        $this->xlsFile = $baseName . '.xls';

        $spreadsheet = $this->createSpreadsheet();

        $xlsxWriter = new XlsxWriter($spreadsheet);
        $xlsxWriter->save($this->xlsxFile);

        $xlsWriter = new XlsWriter($spreadsheet);
        $xlsWriter->save($this->xlsFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->xlsxFile)) {
            unlink($this->xlsxFile);
        }

        if (file_exists($this->xlsFile)) {
            unlink($this->xlsFile);
        }
    }

    public function testOpen(): void
    {
        $xlsx = Xls::open($this->xlsxFile);
        $xls = Xls::open($this->xlsFile);

        $this->assertInstanceOf(Xls::class, $xlsx);
        $this->assertInstanceOf(Xls::class, $xls);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('File not found or not readable.');

        Xls::open('/path/to/file/not/existed.xls');
    }

    public function testReadFromSheetAndStartCell(): void
    {
        $stream = Xls::open($this->xlsxFile);
        $data = iterator_to_array($stream->getStream('Sheet1.G14'));

        $this->assertSame(
            [
                ['Name' => 'Product A', 'Price' => 10],
                ['Name' => 'Product B', 'Price' => 20],
            ],
            $data
        );
    }

    private function createSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');
        $sheet->setCellValue('G14', 'Name');
        $sheet->setCellValue('H14', 'Price');
        $sheet->setCellValue('G15', 'Product A');
        $sheet->setCellValue('H15', 10);
        $sheet->setCellValue('G16', 'Product B');
        $sheet->setCellValue('H16', 20);

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Sheet2');
        $sheet->setCellValue('A1', 'Other');

        return $spreadsheet;
    }
}
