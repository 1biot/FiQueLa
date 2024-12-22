<?php

namespace UQL\Stream;

use UQL\Exceptions\FileNotFoundException;
use UQL\Exceptions\InvalidFormat;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

class Yaml extends StreamProvider
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
            throw new InvalidFormat("Invalid YAML string: " . $e->getMessage());
        }
    }

    /**
     * @throws InvalidFormat
     */
    public static function string(string $data): Stream
    {
        try {
            $parsedData = SymfonyYaml::parse($data);
            $stream = is_array($parsedData) ? new \ArrayIterator($parsedData) : new \ArrayIterator([$parsedData]);
            return new self($stream);
        } catch (ParseException $e) {
            throw new InvalidFormat("Invalid YAML string: " . $e->getMessage());
        }
    }
}
