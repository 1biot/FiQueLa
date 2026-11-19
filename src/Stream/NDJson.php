<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Exception\FileNotFoundException;
use FQL\Interface;

class NDJson extends NDJsonProvider
{
    /**
     * @throws FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw Exception\FileNotFoundException::forPath($path);
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
