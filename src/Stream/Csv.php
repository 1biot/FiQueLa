<?php

namespace UQL\Stream;

use UQL\Exceptions\FileNotFoundException;
use UQL\Exceptions\NotImplemented;
use UQL\Query\Provider;
use UQL\Query\Query;

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
     * @throws NotImplemented
     */
    public static function string(string $data): Stream
    {
        throw new NotImplemented("Method not yet implemented.");
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
