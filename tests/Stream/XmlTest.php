<?php

namespace Stream;

use PHPUnit\Framework\TestCase;
use UQL\Exceptions\FileNotFoundException;
use UQL\Stream\Xml;

class XmlTest extends TestCase
{
    private string $xmlFile;

    protected function setUp(): void
    {
        $this->xmlFile = realpath(__DIR__ . '/../../examples/data/products.xml');
    }

    public function testOpen(): void
    {
        $json = Xml::open($this->xmlFile);
        $this->assertInstanceOf(Xml::class, $json);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File not found or not readable.");

        Xml::open('/path/to/file/not/existed.xml');
    }
}
