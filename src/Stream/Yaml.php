<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use Symfony\Component\Yaml as SymfonyYaml;

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
}
