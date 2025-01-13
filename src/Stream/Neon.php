<?php

namespace FQL\Stream;

use Nette\Neon\Exception;
use FQL\Exceptions\FileNotFoundException;
use FQL\Exceptions\InvalidFormatException;
use FQL\Query\Provider;
use FQL\Query\Query;

class Neon extends ArrayStreamProvider
{
    /**
     * @throws FileNotFoundException
     * @throws InvalidFormatException
     */
    public static function open(string $path): Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new FileNotFoundException("File not found or not readable.");
        }

        try {
            $decoded = \Nette\Neon\Neon::decodeFile($path);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (Exception $e) {
            throw new InvalidFormatException("Invalid NEON string: " . $e->getMessage());
        }
    }

    /**
     * @throws InvalidFormatException
     */
    public static function string(string $data): Stream
    {
        try {
            $decoded = \Nette\Neon\Neon::decode($data);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (Exception $e) {
            throw new InvalidFormatException("Invalid NEON string: " . $e->getMessage());
        }
    }

    public function query(): Query
    {
        return new Provider($this);
    }

    public function provideSource(): string
    {
        return '[neon://memory]';
    }
}
