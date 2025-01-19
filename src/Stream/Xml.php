<?php

namespace FQL\Stream;

use FQL\Exceptions;
use FQL\Interfaces;
use FQL\Query;

class Xml extends XmlProvider implements Interfaces\Stream
{
    /**
     * @throws Exceptions\FileNotFoundException
     */
    public static function openWithEncoding(string $path, ?string $encoding = null): Interfaces\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exceptions\FileNotFoundException("File not found or not readable.");
        }

        $class = new self($path);
        if ($encoding !== null) {
            $class->setInputEncoding($encoding);
        }

        return $class;
    }

    /**
     * @throws Exceptions\FileNotFoundException
     */
    public static function open(string $path): Interfaces\Stream
    {
        return self::openWithEncoding($path);
    }

    /**
     * @throws Exceptions\NotImplementedException
     */
    public static function string(string $data): Interfaces\Stream
    {
        throw new Exceptions\NotImplementedException([__CLASS__, __FUNCTION__]);
    }
}
