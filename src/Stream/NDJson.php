<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Exception\FileNotFoundException;
use FQL\Interface;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
class NDJson extends NDJsonProvider
{
    /**
     * @throws FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
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
            ['unescaped_slashes', 'unescaped_unicode', 'depth'],
            'NDJSON'
        );

        $depth = isset($settings['depth']) ? (int) $settings['depth'] : 512;
        if ($depth <= 0) {
            throw new Exception\UnexpectedValueException('NDJSON depth must be greater than 0');
        }

        $options = 0;
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

        foreach ($data as $item) {
            $encoded = json_encode($item, $options, $depth);
            if ($encoded === false) {
                fclose($handle);
                throw new Exception\UnexpectedValueException('JSON encode error: ' . json_last_error_msg());
            }

            fwrite($handle, $encoded . "\n");
        }

        fclose($handle);
    }
}
