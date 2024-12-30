<?php

namespace UQL\Stream;

use UQL\Exceptions\FileNotFoundException;
use UQL\Exceptions\InvalidFormat;
use UQL\Exceptions\NotImplemented;
use UQL\Query\Provider;
use UQL\Query\Query;

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
     * @throws InvalidFormat
     */
    public static function open(string $path): self
    {
        return self::openWithEncoding($path);
    }

    /**
     * @throws NotImplemented
     */
    public static function string(string $data): Stream
    {
        throw new NotImplemented("Method not implemented.");
    }

    public function query(): Query
    {
        return new Provider($this);
    }
}
