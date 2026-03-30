<?php

namespace Results;

use FQL\Exception\FileAlreadyExistsException;
use FQL\Query\FileQuery;
use FQL\Results\InMemory;
use FQL\Stream\Ods;
use FQL\Stream\Xls;
use PHPUnit\Framework\TestCase;

class ResultsProviderIntoTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/fiquela-results-into-' . uniqid();
        mkdir($this->basePath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);
    }

    public function testIntoWritesCsv(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/data.csv';

        $results->into(sprintf('csv(%s)', $target));

        $this->assertFileExists($target);
        $content = file_get_contents($target);
        $this->assertNotFalse($content);
        $this->assertStringContainsString("NAME,PRICE", $content);
        $this->assertStringContainsString('"Product A",100', $content);
    }

    public function testIntoWritesCsvWithOutputEncoding(): void
    {
        $encoding = 'windows-1250';
        $expectedEncoded = iconv('utf-8', $encoding . '//TRANSLIT', 'Žluťoučký');
        if ($expectedEncoded === false) {
            $this->markTestSkipped(sprintf('Encoding "%s" is not supported by iconv in this environment.', $encoding));
        }

        $results = new InMemory([
            ['NAME' => 'Žluťoučký'],
        ]);
        $target = $this->basePath . '/out/encoded.csv';

        $results->into(sprintf('csv(%s, "%s")', $target, $encoding));

        $this->assertFileExists($target);
        $content = file_get_contents($target);
        $this->assertNotFalse($content);

        $this->assertStringContainsString($expectedEncoded, $content);
    }

    public function testIntoWritesNdJson(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/data.ndjson';

        $results->into(sprintf('ndjson(%s)', $target));

        $this->assertFileExists($target);
        $lines = file($target, FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($lines);
        $this->assertCount(2, $lines);
        $this->assertSame('{"NAME":"Product A","PRICE":100}', $lines[0]);
    }

    public function testIntoWritesNestedJson(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/data.json';

        $results->into(sprintf('json(%s).root.items', $target));

        $decoded = json_decode((string) file_get_contents($target), true);
        $this->assertIsArray($decoded);
        $this->assertSame('Product A', $decoded['root']['items'][0]['NAME']);
    }

    public function testIntoWritesXml(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/data.xml';

        $results->into(sprintf('xml(%s).SHOP.ITEM', $target));

        $content = file_get_contents($target);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('<SHOP>', $content);
        $this->assertStringContainsString('<ITEM>', $content);
        $this->assertStringContainsString('<NAME>Product A</NAME>', $content);
    }

    public function testIntoWritesXlsxWithSheetAndCell(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/data.xlsx';

        $results->into(sprintf('xlsx(%s).Sheet1.B4', $target));

        $stream = Xls::open($target);
        $rows = iterator_to_array($stream->getStream('Sheet1.B4'));

        $this->assertSame(
            [
                ['NAME' => 'Product A', 'PRICE' => 100],
                ['NAME' => 'Product B', 'PRICE' => 200],
            ],
            $rows
        );
    }

    public function testIntoWritesOdsWithSheetAndCell(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/data.ods';

        $results->into(sprintf('ods(%s).Sheet1.B4', $target));

        $stream = Ods::open($target);
        $rows = iterator_to_array($stream->getStream('Sheet1.B4'));

        $this->assertSame(
            [
                ['NAME' => 'Product A', 'PRICE' => 100],
                ['NAME' => 'Product B', 'PRICE' => 200],
            ],
            $rows
        );
    }

    public function testIntoJsonWithStarQueryWritesFlatArray(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/star.json';

        $fileQuery = $results->into(sprintf('json(%s).*', $target));

        $decoded = json_decode((string) file_get_contents($target), true);
        $this->assertIsArray($decoded);
        $this->assertArrayNotHasKey('*', $decoded);
        $this->assertSame('Product A', $decoded[0]['NAME']);
        $this->assertSame('Product B', $decoded[1]['NAME']);
        $this->assertSame('*', $fileQuery->query);
    }

    public function testIntoJsonWithoutQueryDefaultsToStar(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/noquery.json';

        $fileQuery = $results->into(sprintf('json(%s)', $target));

        $decoded = json_decode((string) file_get_contents($target), true);
        $this->assertIsArray($decoded);
        $this->assertSame('Product A', $decoded[0]['NAME']);
        $this->assertSame('*', $fileQuery->query);
    }

    public function testIntoCsvWithStarQueryReturnsStarFileQuery(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/star.csv';

        $fileQuery = $results->into(sprintf('csv(%s).*', $target));

        $this->assertFileExists($target);
        $this->assertSame('*', $fileQuery->query);
    }

    public function testIntoCsvWithoutQueryDefaultsToStar(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/noquery.csv';

        $fileQuery = $results->into(sprintf('csv(%s)', $target));

        $this->assertFileExists($target);
        $this->assertSame('*', $fileQuery->query);
    }

    public function testIntoNdJsonWithStarQueryReturnsStarFileQuery(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/star.ndjson';

        $fileQuery = $results->into(sprintf('ndjson(%s).*', $target));

        $this->assertFileExists($target);
        $this->assertSame('*', $fileQuery->query);
        $lines = file($target, FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($lines);
        $this->assertCount(2, $lines);
    }

    public function testIntoXlsxWithStarQueryUsesDefaultSheetAndCell(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/star.xlsx';

        $fileQuery = $results->into(sprintf('xlsx(%s).*', $target));

        $this->assertFileExists($target);
        $stream = Xls::open($target);
        $rows = iterator_to_array($stream->getStream('*'));

        $this->assertSame(
            [
                ['NAME' => 'Product A', 'PRICE' => 100],
                ['NAME' => 'Product B', 'PRICE' => 200],
            ],
            $rows
        );
    }

    public function testIntoOdsWithStarQueryUsesDefaultSheetAndCell(): void
    {
        $results = $this->createResults();
        $target = $this->basePath . '/out/star.ods';

        $fileQuery = $results->into(sprintf('ods(%s).*', $target));

        $this->assertFileExists($target);
        $stream = Ods::open($target);
        $rows = iterator_to_array($stream->getStream('*'));

        $this->assertSame(
            [
                ['NAME' => 'Product A', 'PRICE' => 100],
                ['NAME' => 'Product B', 'PRICE' => 200],
            ],
            $rows
        );
    }

    public function testIntoRejectsExistingFile(): void
    {
        $target = $this->basePath . '/out/data.csv';
        mkdir(dirname($target), 0775, true);
        file_put_contents($target, 'existing');

        $this->expectException(FileAlreadyExistsException::class);

        $this->createResults()->into(sprintf('csv(%s)', $target));
    }

    private function createResults(): InMemory
    {
        return new InMemory([
            ['NAME' => 'Product A', 'PRICE' => 100],
            ['NAME' => 'Product B', 'PRICE' => 200],
        ]);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
