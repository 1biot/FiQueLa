<?php

namespace FQL\Stream;

use FQL\Exceptions;
use FQL\Interfaces;
use Symfony\Component\Yaml as SymfonyYaml;

class Yaml extends ArrayStreamProvider
{
    /**
     * @param string $path
     * @return Yaml
     * @throws Exceptions\FileNotFoundException
     * @throws Exceptions\InvalidFormatException
     */
    public static function open(string $path): Interfaces\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exceptions\FileNotFoundException('File not found or not readable.');
        }

        try {
            $parsedData = SymfonyYaml\Yaml::parseFile(
                $path,
                SymfonyYaml\Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
            );
            $stream = is_array($parsedData) ? new \ArrayIterator($parsedData) : new \ArrayIterator([$parsedData]);
            return new self($stream);
        } catch (SymfonyYaml\Exception\ParseException $e) {
            throw new Exceptions\InvalidFormatException("Invalid YAML string: " . $e->getMessage());
        }
    }

    /**
     * @return Yaml
     * @throws Exceptions\InvalidFormatException
     */
    public static function string(string $data): Interfaces\Stream
    {
        try {
            $parsedData = SymfonyYaml\Yaml::parse($data);
            $stream = is_array($parsedData) ? new \ArrayIterator($parsedData) : new \ArrayIterator([$parsedData]);
            return new self($stream);
        } catch (SymfonyYaml\Exception\ParseException $e) {
            throw new Exceptions\InvalidFormatException("Invalid YAML string: " . $e->getMessage());
        }
    }

    public function provideSource(): string
    {
        return '[yaml](memory)';
    }
}
