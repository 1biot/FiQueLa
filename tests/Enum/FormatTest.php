<?php

namespace Enum;

use FQL\Enum\Format;
use FQL\Exception\InvalidFormatException;
use FQL\Stream;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    /**
     * @dataProvider extensionProvider
     */
    public function testFromExtension(string $extension, Format $expected): void
    {
        $this->assertSame($expected, Format::fromExtension($extension));
    }

    public function testFromExtensionThrowsOnUnsupported(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Unsupported file format "unknown"');

        Format::fromExtension('unknown');
    }

    /**
     * @dataProvider providerClassProvider
     */
    public function testGetFormatProviderClass(Format $format, string $expectedClass): void
    {
        $this->assertSame($expectedClass, $format->getFormatProviderClass());
    }

    /**
     * @dataProvider fromStringProvider
     */
    public function testFromString(Format $format, string $data, string $expectedClass): void
    {
        $stream = $format->fromString($data);
        $this->assertInstanceOf($expectedClass, $stream);
    }

    public function testFromStringThrowsOnUnsupported(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Unsupported format');

        Format::CSV->fromString('a,b');
    }

    /**
     * @return array<string, array{0: string, 1: Format}>
     */
    public static function extensionProvider(): array
    {
        return [
            'xml' => ['xml', Format::XML],
            'json' => ['json', Format::JSON_STREAM],
            'json stream' => ['jsonFile', Format::JSON_STREAM],
            'ndjson' => ['ndjson', Format::ND_JSON],
            'ndjson camel' => ['ndJson', Format::ND_JSON],
            'csv' => ['csv', Format::CSV],
            'tsv' => ['tsv', Format::CSV],
            'yaml' => ['yaml', Format::YAML],
            'yml' => ['yml', Format::YAML],
            'neon' => ['neon', Format::NEON],
            'xls' => ['xls', Format::XLS],
            'xlsx' => ['xlsx', Format::XLS],
            'dir' => ['dir', Format::DIR],
        ];
    }

    /**
     * @return array<string, array{0: Format, 1: class-string}>
     */
    public static function providerClassProvider(): array
    {
        return [
            'xml' => [Format::XML, Stream\Xml::class],
            'json' => [Format::JSON, Stream\Json::class],
            'json stream' => [Format::JSON_STREAM, Stream\JsonStream::class],
            'ndjson' => [Format::ND_JSON, Stream\NDJson::class],
            'csv' => [Format::CSV, Stream\Csv::class],
            'yaml' => [Format::YAML, Stream\Yaml::class],
            'neon' => [Format::NEON, Stream\Neon::class],
            'xls' => [Format::XLS, Stream\Xls::class],
            'dir' => [Format::DIR, Stream\Dir::class],
        ];
    }

    /**
     * @return array<string, array{0: Format, 1: string, 2: class-string}>
     */
    public static function fromStringProvider(): array
    {
        return [
            'json' => [Format::JSON, '{"a": 1}', Stream\Json::class],
            'yaml' => [Format::YAML, "a: 1\n", Stream\Yaml::class],
            'neon' => [Format::NEON, "a: 1\n", Stream\Neon::class],
        ];
    }
}
