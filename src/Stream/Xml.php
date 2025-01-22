<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use FQL\Query;

class Xml extends XmlProvider implements Interface\Stream
{
    /**
     * @throws Exception\FileNotFoundException
     */
    public static function openWithEncoding(string $path, ?string $encoding = null): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException("File not found or not readable.");
        }

        $class = new self($path);
        if ($encoding !== null) {
            $class->setInputEncoding($encoding);
        }

        return $class;
    }

    /**
     * @throws Exception\FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        return self::openWithEncoding($path);
    }

    /**
     * @throws Exception\NotImplementedException
     */
    public static function string(string $data): Interface\Stream
    {
        throw new Exception\NotImplementedException([__CLASS__, __FUNCTION__]);
    }
}
