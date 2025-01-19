<?php

namespace FQL\Stream;

use FQL\Exceptions;
use FQL\Interfaces;

class Csv extends CsvProvider
{
    /**
     * @throws Exceptions\FileNotFoundException
     */
    public static function open(string $path): Interfaces\Stream
    {
        return self::openWithDelimiter($path);
    }

    /**
     * @throws Exceptions\NotImplementedException
     */
    public static function string(string $data): Interfaces\Stream
    {
        throw new Exceptions\NotImplementedException([__CLASS__, __FUNCTION__]);
    }

    /**
     * @throws Exceptions\FileNotFoundException
     */
    public static function openWithDelimiter(string $path, ?string $delimiter = null): Interfaces\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exceptions\FileNotFoundException("File not found or not readable.");
        }

        return new self($path, $delimiter ?? ',');
    }
}
