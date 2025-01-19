<?php

namespace FQL\Stream;

use FQL\Exceptions;
use FQL\Interfaces;

class JsonStream extends JsonProvider implements Interfaces\Stream
{
    /**
     * @throws Exceptions\FileNotFoundException
     */
    public static function open(string $path): Interfaces\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exceptions\FileNotFoundException("File not found or not readable.");
        }

        return new self($path);
    }

    /**
     * @throws Exceptions\NotImplementedException
     */
    public static function string(string $data): Interfaces\Stream
    {
        throw new Exceptions\NotImplementedException([__CLASS__, __FUNCTION__]);
    }
}
