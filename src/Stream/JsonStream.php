<?php

namespace FQL\Stream;

use FQL\Exceptions\FileNotFoundException;
use FQL\Exceptions\NotImplementedException;
use FQL\Query\Provider;
use FQL\Query\Query;

class JsonStream extends JsonProvider
{
    /**
     * @inheritDoc
     * @throws FileNotFoundException
     */
    public static function open(string $path): Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new FileNotFoundException("File not found or not readable.");
        }

        return new self($path);
    }

    /**
     * @inheritDoc
     * @throws NotImplementedException
     */
    public static function string(string $data): Stream
    {
        throw new NotImplementedException('Method not yet implemented.');
    }

    public function query(): Query
    {
        return new Provider($this);
    }
}
