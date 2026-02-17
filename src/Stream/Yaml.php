<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use Symfony\Component\Yaml as SymfonyYaml;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
class Yaml extends ArrayStreamProvider
{
    /**
     * @param string $path
     * @return Yaml
     * @throws Exception\FileNotFoundException
     * @throws Exception\InvalidFormatException
     */
    public static function open(string $path): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException('File not found or not readable.');
        }

        try {
            $parsedData = SymfonyYaml\Yaml::parseFile(
                $path,
                SymfonyYaml\Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
            );
            $stream = is_array($parsedData) ? new \ArrayIterator($parsedData) : new \ArrayIterator([$parsedData]);
            return new self($stream);
        } catch (SymfonyYaml\Exception\ParseException $e) {
            throw new Exception\InvalidFormatException("Invalid YAML string: " . $e->getMessage());
        }
    }

    /**
     * @return Yaml
     * @throws Exception\InvalidFormatException
     */
    public static function string(string $data): Interface\Stream
    {
        try {
            $parsedData = SymfonyYaml\Yaml::parse($data);
            $stream = is_array($parsedData) ? new \ArrayIterator($parsedData) : new \ArrayIterator([$parsedData]);
            return new self($stream);
        } catch (SymfonyYaml\Exception\ParseException $e) {
            throw new Exception\InvalidFormatException("Invalid YAML string: " . $e->getMessage());
        }
    }

    public function provideSource(): string
    {
        return '[yaml](memory)';
    }

    /**
     * @param StreamProviderArrayIterator $data
     * @param array<string, mixed> $settings
     * @throws Exception\UnexpectedValueException
     * @throws Exception\UnableOpenFileException
     */
    public static function write(string $fileName, \Traversable $data, array $settings = []): void
    {
        self::assertAllowedSettings(
            $settings,
            ['indent', 'inline', 'flags'],
            'YAML'
        );

        $indent = (int) ($settings['indent'] ?? 2);
        $inline = (int) ($settings['inline'] ?? 4);
        $flags = (int) ($settings['flags'] ?? 0);
        $allowedFlags = [
            0,
            SymfonyYaml\Yaml::DUMP_OBJECT,
            SymfonyYaml\Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE,
            SymfonyYaml\Yaml::DUMP_OBJECT_AS_MAP,
            SymfonyYaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
            SymfonyYaml\Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE,
            SymfonyYaml\Yaml::DUMP_NULL_AS_TILDE,
            SymfonyYaml\Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            SymfonyYaml\Yaml::DUMP_NULL_AS_EMPTY,
            SymfonyYaml\Yaml::DUMP_COMPACT_NESTED_MAPPING,
            SymfonyYaml\Yaml::DUMP_FORCE_DOUBLE_QUOTES_ON_VALUES,
        ];

        if (!in_array($flags, $allowedFlags, true)) {
            throw new Exception\UnexpectedValueException('Unsupported YAML flags value');
        }

        $dataArray = iterator_to_array($data);
        $yaml = SymfonyYaml\Yaml::dump($dataArray, $inline, $indent, $flags);
        if (file_put_contents($fileName, $yaml) === false) {
            throw new Exception\UnableOpenFileException(sprintf('Unable to open file: %s', $fileName));
        }
    }
}
