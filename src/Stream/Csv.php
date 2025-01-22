<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;

class Csv extends CsvProvider
{
    /**
     * @throws Exception\FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        return self::openWithDelimiter($path);
    }

    /**
     * @throws Exception\NotImplementedException
     */
    public static function string(string $data): Interface\Stream
    {
        throw new Exception\NotImplementedException([__CLASS__, __FUNCTION__]);
    }

    /**
     * @throws Exception\FileNotFoundException
     */
    public static function openWithDelimiter(string $path, ?string $delimiter = null): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException("File not found or not readable.");
        }

        return new self($path, $delimiter ?? ',');
    }
}
