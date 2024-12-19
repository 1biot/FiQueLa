<?php

namespace JQL;

use ArrayIterator;
use JQL\Exceptions\FileNotFoundException;
use JQL\Exceptions\InvalidArgumentException;
use JQL\Exceptions\InvalidJson;

final class Json
{
    /**
     * @var ArrayIterator<string|int, mixed> $stream
     */
    protected ArrayIterator $stream;

    /**
     * @param ArrayIterator<string|int, mixed> $stream
     */
    private function __construct(ArrayIterator $stream)
    {
        $this->stream = $stream;
    }

    /**
     * @throws InvalidJson
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
     * @throws InvalidJson
     */
    public static function string(string $json): self
    {
        // if (json_validate($json) === false) { // only php >= 8.3
        //     throw new InvalidJson("Invalid JSON string: " . json_last_error_msg());
        // }
        // $decoded = json_decode($json, true);
        // $stream = is_array($decoded) ? new ArrayIterator($decoded) : new ArrayIterator([$decoded]);

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidJson("Invalid JSON string: " . json_last_error_msg());
        }

        $stream = is_array($decoded) ? new ArrayIterator($decoded) : new ArrayIterator([$decoded]);
        return new self($stream);
    }

    /**
     * @return ArrayIterator<string|int, mixed>
     */
    public function getStream(?string $query = null): ArrayIterator
    {
        $keys = $query !== null ? explode('.', $query) : [];
        $stream = new ArrayIterator($this->stream->getArrayCopy());
        foreach ($keys as $key) {
            $stream = $this->applyKeyFilter($stream, $key);
        }
        return $stream;
    }

    public function query(): Query
    {
        return new QueryProvider($this);
    }

    /**
     * @param iterable<string|int, mixed> $stream
     * @param string $key
     * @return ArrayIterator<string|int, mixed>
     * @throws InvalidArgumentException
     */
    private function applyKeyFilter(iterable $stream, string $key): ArrayIterator
    {
        foreach ($stream as $k => $v) {
            if ($k === $key) {
                return is_iterable($v) ? new ArrayIterator($v) : new ArrayIterator([$v]);
            }
        }
        throw new InvalidArgumentException("Key '$key' not found.");
    }
}
