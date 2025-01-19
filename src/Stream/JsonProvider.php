<?php

namespace FQL\Stream;

use FQL\Exceptions;
use JsonMachine as JM;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
abstract class JsonProvider extends StreamProvider
{
    protected function __construct(private readonly string $jsonFilePath)
    {
    }

    /**
     * @param string|null $query
     * @return ?StreamProviderArrayIterator
     */
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
            $items = JM\Items::fromFile(
                $this->jsonFilePath,
                [
                    'pointer' => $this->convertDotNotationToJsonPointer($query),
                    'decoder' => new JM\JsonDecoder\ExtJsonDecoder(true),
                ]
            );
        } catch (JM\Exception\InvalidArgumentException $e) {
            throw new Exceptions\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        yield from $items;
    }

    private function convertDotNotationToJsonPointer(string $dotNotation): string
    {
        $pointer = '';
        $parts = explode('.', $dotNotation);
        foreach ($parts as $part) {
            // If $part is numeric, process it as an array index
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
            $source = sprintf('[json](%s)', basename($this->jsonFilePath));
        }
        return $source;
    }
}
