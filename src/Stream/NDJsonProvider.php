<?php

namespace FQL\Stream;

use FQL\Exception;

abstract class NDJsonProvider extends AbstractStream
{
    public function __construct(private readonly string $ndJsonFilePath)
    {
    }

    public function getStream(?string $query): \ArrayIterator
    {
        return new \ArrayIterator(iterator_to_array($this->getStreamGenerator($query)));
    }
    /**
     * @throws Exception\UnableOpenFileException
     */
    public function getStreamGenerator(?string $query): \Generator
    {
        $ndjson = new \SplFileObject($this->ndJsonFilePath, 'r');
        if (!$ndjson->isFile() || !$ndjson->isReadable()) {
            throw new Exception\UnableOpenFileException('Unable to open JSON file.');
        }

        $ndjson->setFlags(\SplFileObject::DROP_NEW_LINE);
        foreach ($ndjson as $line) {
            if ($line === false || !is_string($line)) {
                break;
            }

            if (trim($line) === '') {
                break;
            }

            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                yield $data;
            }
        }
    }

    public function provideSource(): string
    {
        return sprintf('[ndjson](%s)', $this->ndJsonFilePath);
    }
}
