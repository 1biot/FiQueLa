<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;

final class Json extends ArrayStreamProvider
{
    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException("File not found or not readable.");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new Exception\FileNotFoundException("Could not open file");
        }

        $content = '';
        while (!feof($handle)) {
            $content .= fread($handle, 8192);
        }

        fclose($handle);
        return self::string($content);
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public static function string(string $data): Interface\Stream
    {
        // if (json_validate($json) === false) { // only php >= 8.3
        //     throw new InvalidJson("Invalid JSON string: " . json_last_error_msg());
        // }
        // $decoded = json_decode($json, true);
        // $stream = is_array($decoded) ? new ArrayIterator($decoded) : new ArrayIterator([$decoded]);

        $decoded = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception\InvalidFormatException("Invalid JSON string: " . json_last_error_msg());
        }

        $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
        return new self($stream);
    }

    public function provideSource(): string
    {
        return '[json](memory)';
    }
}
