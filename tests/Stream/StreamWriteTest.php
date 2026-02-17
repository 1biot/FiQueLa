<?php

namespace Stream;

use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class StreamWriteTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fql-stream-write-' . uniqid('', true);
        mkdir($this->baseDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->baseDir);
    }

    public function testJsonWrite(): void
    {
        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . 'products.json';
        $data = new \ArrayIterator([
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 2, 'name' => 'Beta'],
        ]);

        Json::write($filePath, $data, ['pretty' => true]);

        $contents = file_get_contents($filePath);
        $this->assertIsString($contents);
        $this->assertStringContainsString('"id": 1', $contents);
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
