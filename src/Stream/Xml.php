<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
class Xml extends XmlProvider implements Interface\Stream
{
    /**
     * @throws Exception\FileNotFoundException
     */
    public static function openWithEncoding(string $path, ?string $encoding = null): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException("File not found or not readable.");
        }

        $class = new self($path);
        if ($encoding !== null) {
            $class->setInputEncoding($encoding);
        }

        return $class;
    }

    /**
     * @throws Exception\FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        return self::openWithEncoding($path);
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
            ['root', 'item', 'encoding', 'pretty'],
            'XML'
        );

        $rootName = (string) ($settings['root'] ?? 'rows');
        $itemName = (string) ($settings['item'] ?? 'row');
        $encoding = (string) ($settings['encoding'] ?? 'utf-8');
        $pretty = (bool) ($settings['pretty'] ?? false);

        $writer = new \XMLWriter();
        if (!$writer->openURI($fileName)) {
            throw new Exception\UnableOpenFileException(sprintf('Unable to open file: %s', $fileName));
        }

        $writer->startDocument('1.0', $encoding);
        $writer->setIndent($pretty);
        $writer->setIndentString('  ');
        $writer->startElement($rootName);

        foreach ($data as $item) {
            $writer->startElement($itemName);

            $row = $item;
            foreach ($row as $key => $value) {
                self::appendXmlField($writer, (string) $key, $value);
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
    }

    private static function appendXmlField(\XMLWriter $writer, string $name, mixed $value): void
    {
        $elementName = self::isValidXmlName($name) ? $name : 'field';
        $writer->startElement($elementName);
        if ($elementName === 'field') {
            $writer->writeAttribute('name', $name);
        }

        $writer->text(self::normalizeXmlValue($value));
        $writer->endElement();
    }

    private static function normalizeXmlValue(mixed $value): string
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

    private static function isValidXmlName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/', $name);
    }
}
