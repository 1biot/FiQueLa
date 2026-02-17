<?php

namespace SQL;

use FQL\Exception\InvalidFormatException;
use FQL\Exception\NotImplementedException;
use FQL\Exception\UnexpectedValueException;
use FQL\Sql\Sql;
use PHPUnit\Framework\TestCase;

class SqlIntoTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fql-into-' . uniqid('', true);
        mkdir($this->baseDir, 0775, true);

        $source = realpath(__DIR__ . '/../../examples/data/products.json');
        if ($source === false) {
            self::fail('Unable to resolve example products.json');
        }

        copy($source, $this->baseDir . DIRECTORY_SEPARATOR . 'products.json');
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->baseDir);
    }

    public function testIntoNdjsonWithBasePath(): void
    {
        $sql = 'SELECT id, name FROM [json](products.json).data.products '
            . 'INTO "exports/products.ndjson" WITH (format = "ndjson")';

        $parser = new Sql($sql, $this->baseDir);
        $parser->parse();

        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'products.ndjson';
        $this->assertFileExists($filePath);

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertIsArray($lines);
        $this->assertCount(5, $lines);

        $first = json_decode($lines[0], true);
        $this->assertIsArray($first);
        $this->assertSame(1, $first['id'] ?? null);
    }

    public function testIntoCsvWithSettings(): void
    {
        $sql = 'SELECT id, name FROM [json](products.json).data.products '
            . 'INTO "exports/products.csv" WITH (format = "csv", header = true, delimiter = ";", encoding = "utf-8")';

        $parser = new Sql($sql, $this->baseDir);
        $parser->parse();

        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'products.csv';
        $this->assertFileExists($filePath);

        $contents = file_get_contents($filePath);
        $this->assertIsString($contents);
        $this->assertStringStartsWith("id;name", $contents);
    }

    public function testIntoJsonWithSettings(): void
    {
        $sql = 'SELECT id, name FROM [json](products.json).data.products '
            . 'INTO "exports/products.json" WITH (format = "json", pretty = true, unescaped_unicode = true)';

        $parser = new Sql($sql, $this->baseDir);
        $parser->parse();

        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'products.json';
        $this->assertFileExists($filePath);

        $contents = file_get_contents($filePath);
        $this->assertIsString($contents);
        $this->assertStringStartsWith("[", $contents);
        $this->assertStringContainsString("\n", $contents);
    }

    public function testIntoCsvWithoutHeader(): void
    {
        $sql = 'SELECT id, name FROM [json](products.json).data.products '
            . 'INTO "exports/products-no-header.csv" WITH (format = "csv", header = false, delimiter = ",")';

        $parser = new Sql($sql, $this->baseDir);
        $parser->parse();

        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'products-no-header.csv';
        $this->assertFileExists($filePath);

        $contents = file_get_contents($filePath);
        $this->assertIsString($contents);
        $this->assertStringStartsNotWith("id,", $contents);
    }

    public function testIntoXmlWithSettings(): void
    {
        $sql = 'SELECT id, categories, name AS `1name` FROM [json](products.json).data.products '
            . 'INTO "exports/products.xml" WITH (format = "xml", root = "products", item = "product", pretty = true)';

        $parser = new Sql($sql, $this->baseDir);
        $parser->parse();

        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'products.xml';
        $this->assertFileExists($filePath);

        $contents = file_get_contents($filePath);
        $this->assertIsString($contents);
        $this->assertStringContainsString('<products>', $contents);
        $this->assertStringContainsString('<product>', $contents);
        $this->assertStringContainsString('<categories>', $contents);
        $this->assertStringContainsString('name="1name"', $contents);
    }

    public function testIntoYamlWithSettings(): void
    {
        $sql = 'SELECT id, name FROM [json](products.json).data.products '
            . 'INTO "exports/products.yaml" WITH (format = "yaml", indent = 2, inline = 2, flags = 0)';

        $parser = new Sql($sql, $this->baseDir);
        $parser->parse();

        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'products.yaml';
        $this->assertFileExists($filePath);

        $contents = file_get_contents($filePath);
        $this->assertIsString($contents);
        $this->assertStringContainsString('id:', $contents);
    }

    public function testIntoNeonWithSettings(): void
    {
        $sql = 'SELECT id, name FROM [json](products.json).data.products '
            . 'INTO "exports/products.neon" WITH (format = "neon", block = true, indent = 2)';

        $parser = new Sql($sql, $this->baseDir);
        $parser->parse();

        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'products.neon';
        $this->assertFileExists($filePath);

        $contents = file_get_contents($filePath);
        $this->assertIsString($contents);
        $this->assertStringContainsString('id:', $contents);
    }

    public function testIntoXlsNotImplemented(): void
    {
        $sql = 'SELECT id FROM [json](products.json).data.products '
            . 'INTO "exports/products.xls" WITH (format = "xls")';

        $parser = new Sql($sql, $this->baseDir);

        $this->expectException(NotImplementedException::class);
        $parser->parse();
    }

    public function testIntoCsvUnexpectedSetting(): void
    {
        $sql = 'SELECT id FROM [json](products.json).data.products '
            . 'INTO "exports/products.csv" WITH (format = "csv", foo = 1)';

        $parser = new Sql($sql, $this->baseDir);

        $this->expectException(UnexpectedValueException::class);
        $parser->parse();
    }

    public function testIntoRejectsOutsideBasePath(): void
    {
        $sql = 'SELECT id FROM [json](products.json).data.products INTO "../outside.json"';

        $parser = new Sql($sql, $this->baseDir);

        $this->expectException(InvalidFormatException::class);
        $parser->parse();
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
