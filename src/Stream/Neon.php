<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use Nette\Neon as NeonProvider;

class Neon extends ArrayStreamProvider
{
    /**
     * @throws Exception\FileNotFoundException
     * @throws Exception\InvalidFormatException
     */
    public static function open(string $path): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException("File not found or not readable.");
        }

        try {
            $decoded = \Nette\Neon\Neon::decodeFile($path);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (NeonProvider\Exception $e) {
            throw new Exception\InvalidFormatException("Invalid NEON string: " . $e->getMessage());
        }
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public static function string(string $data): Interface\Stream
    {
        try {
            $decoded = \Nette\Neon\Neon::decode($data);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (NeonProvider\Exception $e) {
            throw new Exception\InvalidFormatException("Invalid NEON string: " . $e->getMessage());
        }
    }

    public function provideSource(): string
    {
        return '[neon](memory)';
    }
}
