<?php

namespace FQL\Stream;

use FQL\Exceptions;
use FQL\Interfaces;

final class Json extends ArrayStreamProvider
{
    /**
     * @throws Exceptions\InvalidFormatException
     * @throws Exceptions\FileNotFoundException
     */
    public static function open(string $path): Interfaces\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exceptions\FileNotFoundException("File not found or not readable.");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new Exceptions\FileNotFoundException("Could not open file");
        }

        $content = '';
        while (!feof($handle)) {
            $content .= fread($handle, 8192);
        }

        fclose($handle);
        return self::string($content);
    }

    /**
     * @throws Exceptions\InvalidFormatException
     */
    public static function string(string $data): Interfaces\Stream
    {
        // if (json_validate($json) === false) { // only php >= 8.3
        //     throw new InvalidJson("Invalid JSON string: " . json_last_error_msg());
        // }
        // $decoded = json_decode($json, true);
        // $stream = is_array($decoded) ? new ArrayIterator($decoded) : new ArrayIterator([$decoded]);

        $decoded = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exceptions\InvalidFormatException("Invalid JSON string: " . json_last_error_msg());
        }

        $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
        return new self($stream);
    }

    public function provideSource(): string
    {
        return '[json](memory)';
    }
}
