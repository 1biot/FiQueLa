<?php

namespace FQL\Stream;

use JsonMachine\Exception\InvalidArgumentException;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

/**
 * @implements Stream<\Generator>
 */
abstract class JsonProvider extends StreamProvider implements Stream
{
    protected function __construct(private readonly string $jsonFilePath)
    {
    }

    public function getStream(?string $query): ?\ArrayIterator
    {
        $generator = $this->getStreamGenerator($query);
        return $generator ? new \ArrayIterator(iterator_to_array($generator)) : null;
    }

    public function getStreamGenerator(?string $query): ?\Generator
    {
        if ($query === null) {
            return null;
        }

        try {
            $items = Items::fromFile(
                $this->jsonFilePath,
                [
                    'pointer' => $this->convertDotNotationToJsonPointer($query),
                    'decoder' => new ExtJsonDecoder(true),
                ]
            );
        } catch (InvalidArgumentException $e) {
            throw new \FQL\Exceptions\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        yield from $items;
    }

    private function convertDotNotationToJsonPointer(string $dotNotation): string
    {
        $parts = explode('.', $dotNotation);
        $pointer = '';

        foreach ($parts as $part) {
            // Pokud je část číselná, zpracujeme ji jako index pole
            if (ctype_digit($part)) {
                $pointer .= '/' . $part;
            } else {
                $pointer .= '/' . str_replace('~', '~0', str_replace('/', '~1', $part));
            }
        }

        return $pointer;
    }

    public function provideSource(): string
    {
        $source = '';
        if ($this->jsonFilePath !== '') {
            if (pathinfo($this->jsonFilePath, PATHINFO_EXTENSION) !== 'json') {
                $source .= 'json://';
            }
            $source .= basename($this->jsonFilePath);
            $source = sprintf('[%s]', $source);
        }
        return $source;
    }
}
