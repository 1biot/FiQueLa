<?php

namespace FQL\Stream;

use FQL\Exception;
use JsonMachine as JM;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
abstract class JsonProvider extends AbstractStream
{
    protected function __construct(private readonly string $jsonFilePath)
    {
    }

    /**
     * @param string|null $query
     * @return StreamProviderArrayIterator
     */
    public function getStream(?string $query): \ArrayIterator
    {
        return new \ArrayIterator(iterator_to_array($this->getStreamGenerator($query)));
    }

    public function getStreamGenerator(?string $query): \Generator
    {
        $query = $query ?? '';

        $handle = fopen($this->jsonFilePath, 'r');
        try {
            yield from JM\Items::fromStream(
                $handle,
                [
                    'pointer' => $this->convertDotNotationToJsonPointer($query),
                    'decoder' => new JM\JsonDecoder\ExtJsonDecoder(true),
                ]
            );
        } catch (JM\Exception\InvalidArgumentException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        } finally {
            fclose($handle);
        }
    }

    private function convertDotNotationToJsonPointer(string $dotNotation): string
    {
        $pointer = '';
        $parts = array_filter(explode('.', $dotNotation));
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
