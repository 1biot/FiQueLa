<?php

namespace Stream;

use FQL\Exception\FileNotFoundException;
use FQL\Stream\NDJson;
use PHPUnit\Framework\TestCase;

class NDJsonTest extends TestCase
{
    private string $ndJsonFile;

    protected function setUp(): void
    {
        $this->ndJsonFile = realpath(__DIR__ . '/../../examples/data/ndjson-sample.json');
    }

    public function testOpen(): void
    {
        $json = NDJson::open($this->ndJsonFile);
        $this->assertInstanceOf(NDJson::class, $json);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File not found or not readable.");

        NDJson::open('/path/to/file/not/existed.json');
    }
}
