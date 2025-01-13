<?php

namespace FQL\Stream;

use FQL\Exceptions\FileNotFoundException;
use FQL\Exceptions\NotImplementedException;
use FQL\Query\Provider;
use FQL\Query\Query;

class Csv extends CsvProvider
{
    /**
     * @throws FileNotFoundException
     * @return Csv
     */
    public static function open(string $path): Stream
    {
        return self::openWithDelimiter($path);
    }

    /**
     * @throws NotImplementedException
     */
    public static function string(string $data): Stream
    {
        throw new NotImplementedException("Method not yet implemented.");
    }

    /**
     * @throws FileNotFoundException
     */
    public static function openWithDelimiter(string $path, ?string $delimiter = null): self
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new FileNotFoundException("File not found or not readable.");
        }

        return new self($path, $delimiter ?? ',');
    }

    public function query(): Query
    {
        return new Provider($this);
    }
}
