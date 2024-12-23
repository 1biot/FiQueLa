<?php

namespace UQL\Stream;

use UQL\Exceptions\FileNotFoundException;
use UQL\Exceptions\InvalidFormat;
use Nette\Neon\Exception;
use UQL\Query\Provider;
use UQL\Query\Query;

class Neon extends ArrayStreamProvider
{
    /**
     * @throws FileNotFoundException
     * @throws InvalidFormat
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
            throw new InvalidFormat("Invalid NEON string: " . $e->getMessage());
        }
    }

    /**
     * @throws InvalidFormat
     */
    public static function string(string $data): Stream
    {
        try {
            $decoded = \Nette\Neon\Neon::decode($data);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (Exception $e) {
            throw new InvalidFormat("Invalid NEON string: " . $e->getMessage());
        }
    }

    public function query(): Query
    {
        return new Provider($this);
    }
}
