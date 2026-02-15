<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use Nette\Neon as NeonProvider;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
class Neon extends ArrayStreamProvider
{
    /**
     * @throws Exception\FileNotFoundException
     * @throws Exception\InvalidFormatException
     */
    public static function open(string $path): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException("File not found or not readable.");
        }

        try {
            $decoded = \Nette\Neon\Neon::decodeFile($path);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (NeonProvider\Exception $e) {
            throw new Exception\InvalidFormatException("Invalid NEON string: " . $e->getMessage());
        }
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public static function string(string $data): Interface\Stream
    {
        try {
            $decoded = \Nette\Neon\Neon::decode($data);
            $stream = is_array($decoded) ? new \ArrayIterator($decoded) : new \ArrayIterator([$decoded]);
            return new self($stream);
        } catch (NeonProvider\Exception $e) {
            throw new Exception\InvalidFormatException("Invalid NEON string: " . $e->getMessage());
        }
    }

    public function provideSource(): string
    {
        return '[neon](memory)';
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
            ['block', 'indent'],
            'NEON'
        );

        $block = (bool) ($settings['block'] ?? false);
        $indent = $settings['indent'] ?? "\t";
        if (is_int($indent)) {
            $indent = str_repeat(' ', max(0, $indent));
        }

        $indentString = (string) $indent;
        if ($indentString === '') {
            $indentString = "\t";
        }

        $dataArray = iterator_to_array($data);
        $neon = NeonProvider\Neon::encode($dataArray, $block, $indentString);
        if (file_put_contents($fileName, $neon) === false) {
            throw new Exception\UnableOpenFileException(sprintf('Unable to open file: %s', $fileName));
        }
    }
}
