<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;

class Dir extends DirectoryProvider
{
    /**
     * @throws Exception\FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false || is_dir($path) === false) {
            throw new Exception\FileNotFoundException("File not found or not readable.");
        }

        return new self($path);
    }

    /**
     * @throws Exception\NotImplementedException
     */
    public static function string(string $data): Interface\Stream
    {
        throw new Exception\NotImplementedException([__CLASS__, __FUNCTION__]);
    }
}
