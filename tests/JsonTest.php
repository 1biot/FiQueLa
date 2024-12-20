<?php

namespace JQL;

use JQL\Enum\Operator;
use JQL\Enum\Sort;
use JQL\Exceptions\FileNotFoundException;
use JQL\Exceptions\InvalidArgumentException;
use JQL\Exceptions\InvalidJson;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
    private string $jsonFile;
    private string $invalidJsonFile;
    private string $invalidJsonString;

    protected function setUp(): void
    {
        $this->jsonFile = realpath(__DIR__ . '/../examples/products.json');
        $this->invalidJsonFile = realpath(__DIR__ . '/../examples/invalid.json');
        $this->invalidJsonString = '{"data": {"products": [invalid json}';
    }

    public function testOpen(): void
    {
        $json = Json::open($this->jsonFile);
        $this->assertInstanceOf(Json::class, $json);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File not found or not readable.");

        Json::open('/path/to/file/not/existed.json');
    }

    public function testOpenInvalidJsonFile(): void
    {
        $this->expectException(InvalidJson::class);
        $this->expectExceptionMessage("Invalid JSON string");

        Json::open($this->invalidJsonFile);
    }

    public function testStringInvalidJson(): void
    {
        $this->expectException(InvalidJson::class);
        $this->expectExceptionMessage("Invalid JSON string");

        Json::string($this->invalidJsonString);
    }
}
