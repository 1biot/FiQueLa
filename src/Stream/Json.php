<?php

namespace UQL\Stream;

use ArrayIterator;
use UQL\Exceptions\FileNotFoundException;
use UQL\Exceptions\InvalidFormatException;
use UQL\Query\Provider;
use UQL\Query\Query;

final class Json extends ArrayStreamProvider
{
    /**
     * @throws InvalidFormatException
     * @throws FileNotFoundException
     */
    public static function open(string $path): self
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new FileNotFoundException("File not found or not readable.");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new FileNotFoundException("Could not open file");
        }

        $content = '';
        while (!feof($handle)) {
            $content .= fread($handle, 8192);
        }

        fclose($handle);
        return self::string($content);
    }

    /**
     * @throws InvalidFormatException
     */
    public static function string(string $data): self
    {
        // if (json_validate($json) === false) { // only php >= 8.3
        //     throw new InvalidJson("Invalid JSON string: " . json_last_error_msg());
        // }
        // $decoded = json_decode($json, true);
        // $stream = is_array($decoded) ? new ArrayIterator($decoded) : new ArrayIterator([$decoded]);

        $decoded = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidFormatException("Invalid JSON string: " . json_last_error_msg());
        }

        $stream = is_array($decoded) ? new ArrayIterator($decoded) : new ArrayIterator([$decoded]);
        return new self($stream);
    }

    public function query(): Query
    {
        return new Provider($this);
    }

    public function provideSource(): string
    {
        return '[json://memory]';
    }
}
