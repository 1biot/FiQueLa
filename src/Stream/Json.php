<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
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

    /**
     * @param StreamProviderArrayIterator $data
     * @param array<string, mixed> $settings
     * @throws Exception\UnexpectedValueException
     * @throws Exception\UnableOpenFileException
     */
    public static function write(string $fileName, \Traversable $data, array $settings = []): void
    {
        self::assertAllowedSettings(
            $settings,
            ['pretty', 'unescaped_slashes', 'unescaped_unicode', 'depth'],
            'JSON'
        );

        $depth = isset($settings['depth']) ? (int) $settings['depth'] : 512;
        if ($depth <= 0) {
            throw new Exception\UnexpectedValueException('JSON depth must be greater than 0');
        }

        $options = 0;
        if (!empty($settings['pretty'])) {
            $options |= JSON_PRETTY_PRINT;
        }
        if (!empty($settings['unescaped_slashes'])) {
            $options |= JSON_UNESCAPED_SLASHES;
        }
        if (!empty($settings['unescaped_unicode'])) {
            $options |= JSON_UNESCAPED_UNICODE;
        }

        $handle = fopen($fileName, 'wb');
        if ($handle === false) {
            throw new Exception\UnableOpenFileException(sprintf('Unable to open file: %s', $fileName));
        }

        $first = true;
        fwrite($handle, '[');
        foreach ($data as $item) {
            $encoded = json_encode($item, $options, $depth);
            if ($encoded === false) {
                fclose($handle);
                throw new Exception\UnexpectedValueException('JSON encode error: ' . json_last_error_msg());
            }

            if (!$first) {
                fwrite($handle, ',');
            }

            fwrite($handle, $encoded);
            $first = false;
        }
        fwrite($handle, ']');
        fclose($handle);
    }
}
