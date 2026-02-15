<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use League\Csv\CharsetConverter;
use League\Csv\Writer;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
class Csv extends CsvProvider
{
    /**
     * @throws Exception\FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        return self::openWithDelimiter($path);
    }

    /**
     * @throws Exception\NotImplementedException
     */
    public static function string(string $data): Interface\Stream
    {
        throw new Exception\NotImplementedException([__CLASS__, __FUNCTION__]);
    }

    /**
     * @throws Exception\FileNotFoundException
     */
    public static function openWithDelimiter(string $path, ?string $delimiter = null): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException("File not found or not readable.");
        }

        return new self($path, $delimiter ?? ',');
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
            ['delimiter', 'header', 'encoding'],
            'CSV'
        );

        $delimiter = (string) ($settings['delimiter'] ?? ',');
        if ($delimiter === '') {
            throw new Exception\UnexpectedValueException('CSV delimiter cannot be empty');
        }

        $useHeader = (bool) ($settings['header'] ?? true);
        $encoding = (string) ($settings['encoding'] ?? 'UTF-8');

        $writer = Writer::from($fileName, 'w+');
        $writer->setDelimiter($delimiter);
        if ($encoding !== '' && strtoupper($encoding) !== 'UTF-8') {
            CharsetConverter::addTo($writer, 'UTF-8', $encoding);
        }

        $headerWritten = false;
        $headerKeys = [];

        foreach ($data as $item) {
            $row = $item;
            if ($useHeader && !$headerWritten) {
                $headerKeys = array_keys($row);
                $writer->insertOne($headerKeys);
                $headerWritten = true;
            }

            $writer->insertOne(self::normalizeCsvRow($row, $headerKeys, $useHeader));
        }
    }

    /**
     * @param array<int|string, mixed> $row
     * @param array<int, string|int> $headerKeys
     * @return array<int, string>
     */
    private static function normalizeCsvRow(array $row, array $headerKeys, bool $useHeader): array
    {
        if ($useHeader) {
            $values = [];
            foreach ($headerKeys as $key) {
                $values[] = self::normalizeCsvValue($row[$key] ?? null);
            }
            return $values;
        }

        return array_map(
            fn ($value) => self::normalizeCsvValue($value),
            array_values($row)
        );
    }

    private static function normalizeCsvValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded === false ? '' : $encoded;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
