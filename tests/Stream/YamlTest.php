<?php

namespace Stream;

use PHPUnit\Framework\TestCase;
use FQL\Exceptions\FileNotFoundException;
use FQL\Exceptions\InvalidFormatException;
use FQL\Stream\Yaml;

class YamlTest extends TestCase
{
    private string $yamlFile;
    private string $invalidYamlFile;
    private string $invalidYamlString;

    protected function setUp(): void
    {
        $this->yamlFile = realpath(__DIR__ . '/../../examples/data/products.yaml');
        $this->invalidYamlFile = realpath(__DIR__ . '/../../examples/data/invalid.yaml');
        $this->invalidYamlString = '{"data": {"products": [invalid neon}';
    }

    public function testOpen(): void
    {
        $json = Yaml::open($this->yamlFile);
        $this->assertInstanceOf(Yaml::class, $json);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File not found or not readable.");

        Yaml::open('/path/to/file/not/existed.json');
    }

    public function testOpenInvalidJsonFile(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage("Invalid YAML string");

        Yaml::open($this->invalidYamlFile);
    }

    public function testStringInvalidJson(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage("Invalid YAML string");

        Yaml::string($this->invalidYamlString);
    }
}
