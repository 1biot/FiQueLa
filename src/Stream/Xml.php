<?php

namespace FQL\Stream;

use FQL\Exceptions\FileNotFoundException;
use FQL\Exceptions\InvalidFormatException;
use FQL\Exceptions\NotImplementedException;
use FQL\Query\Provider;
use FQL\Query\Query;

class Xml extends XmlProvider
{
    /**
     * @throws FileNotFoundException
     */
    public static function openWithEncoding(string $path, ?string $encoding = null): self
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new FileNotFoundException("File not found or not readable.");
        }

        return new self($path, $encoding);
    }

    /**
     * @throws FileNotFoundException
     */
    public static function open(string $path): self
    {
        return self::openWithEncoding($path);
    }

    /**
     * @throws NotImplementedException
     */
    public static function string(string $data): Stream
    {
        throw new NotImplementedException("Method not yet implemented.");
    }

    public function query(): Query
    {
        return new Provider($this);
    }
}
