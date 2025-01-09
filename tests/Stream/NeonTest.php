<?php

namespace Stream;

use PHPUnit\Framework\TestCase;
use UQL\Exceptions\FileNotFoundException;
use UQL\Exceptions\InvalidFormatException;
use UQL\Stream\Neon;

class NeonTest extends TestCase
{
    private string $neonFile;
    private string $invalidNeonFile;
    private string $invalidNeonString;

    protected function setUp(): void
    {
        $this->neonFile = realpath(__DIR__ . '/../../examples/data/products.neon');
        $this->invalidNeonFile = realpath(__DIR__ . '/../../examples/data/invalid.neon');
        $this->invalidNeonString = '{"data": {"products": [invalid neon}';
    }

    public function testOpen(): void
    {
        $json = Neon::open($this->neonFile);
        $this->assertInstanceOf(Neon::class, $json);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File not found or not readable.");

        Neon::open('/path/to/file/not/existed.json');
    }

    public function testOpenInvalidJsonFile(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage("Invalid NEON string");

        Neon::open($this->invalidNeonFile);
    }

    public function testStringInvalidJson(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage("Invalid NEON string");

        Neon::string($this->invalidNeonString);
    }
}
