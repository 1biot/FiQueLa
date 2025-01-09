<?php

namespace UQL\Stream;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;
use UQL\Exceptions\FileNotFoundException;
use UQL\Exceptions\InvalidFormatException;
use UQL\Query\Provider;
use UQL\Query\Query;

class Yaml extends ArrayStreamProvider
{
    public static function open(string $path): Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new FileNotFoundException("File not found or not readable.");
        }

        try {
            $parsedData = SymfonyYaml::parseFile(
                $path,
                SymfonyYaml::PARSE_EXCEPTION_ON_INVALID_TYPE
            );
            $stream = is_array($parsedData) ? new \ArrayIterator($parsedData) : new \ArrayIterator([$parsedData]);
            return new self($stream);
        } catch (ParseException $e) {
            throw new InvalidFormatException("Invalid YAML string: " . $e->getMessage());
        }
    }

    /**
     * @throws InvalidFormatException
     */
    public static function string(string $data): Stream
    {
        try {
            $parsedData = SymfonyYaml::parse($data);
            $stream = is_array($parsedData) ? new \ArrayIterator($parsedData) : new \ArrayIterator([$parsedData]);
            return new self($stream);
        } catch (ParseException $e) {
            throw new InvalidFormatException("Invalid YAML string: " . $e->getMessage());
        }
    }

    public function query(): Query
    {
        return new Provider($this);
    }

    public function provideSource(): string
    {
        return '[yaml://memory]';
    }
}
