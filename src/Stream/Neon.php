<?php

namespace FQL\Stream;

use FQL\Exceptions;
use FQL\Interfaces;
use Nette\Neon\Exception;

class Neon extends ArrayStreamProvider
{
    /**
     * @throws Exceptions\FileNotFoundException
     * @throws Exceptions\InvalidFormatException
     */
    public static function open(string $path): Interfaces\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exceptions\FileNotFoundException("File not found or not readable.");
        }

        try {
            $decoded = \Nette\Neon\Neon::decodeFile($path);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (Exception $e) {
            throw new Exceptions\InvalidFormatException("Invalid NEON string: " . $e->getMessage());
        }
    }

    /**
     * @throws Exceptions\InvalidFormatException
     */
    public static function string(string $data): Interfaces\Stream
    {
        try {
            $decoded = \Nette\Neon\Neon::decode($data);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (Exception $e) {
            throw new Exceptions\InvalidFormatException("Invalid NEON string: " . $e->getMessage());
        }
    }

    public function provideSource(): string
    {
        return '[neon](memory)';
    }
}
