<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception;
use FQL\Exception\InvalidFormatException;
use FQL\Interface;

final class FileQuery implements \Stringable
{
    // @codingStandardsIgnoreStart
    private const REGEXP = '(?<fq>(?<fs>(\[(?<t>[a-zA-Z]{2,8})])?(\((?<p>[\w\s\.\-\/]+(\.\w{2,5})?)(?<a>(,\s*(?<e>[a-zA-Z0-9\-]+))(,\s*([\'"])(?<d>.)\%d)?)?\)))?(?<q>^\*|\.*[\w*\.\-\_]{1,})?)';
    // @codingStandardsIgnoreEnd

    public readonly ?Enum\Format $extension;
    public readonly ?string $file;
    public readonly ?string $encoding;
    public readonly ?string $delimiter;
    public readonly ?string $query;

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function __construct(private readonly string $queryPath)
    {
        if (!preg_match('/^' . self::getRegexp() . '$/', $this->queryPath, $matches)) {
            throw new Exception\FileQueryException(sprintf('Invalid query path "%s"', $this->queryPath));
        }

        $extension = $matches['t'] ?? null;
        if ($extension === null || $extension === '') {
            $extension = null;
        }
        $this->extension = $extension !== null ? Enum\Format::fromExtension($extension) : null;

        $filePath = $matches['p'] ?? null;
        if ($filePath === null || $filePath === '') {
            $filePath = null;
        }
        $this->file = $filePath ?? null;

        $this->encoding = $matches['e'] ?? 'utf-8';
        $this->delimiter = $matches['d'] ?? ',';

        $query = $matches['q'] ?? null;
        if ($query !== null) {
            $query = ltrim($query, '.');
        }
        $this->query = $query;
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withFile(?string $file): self
    {
        return new self(self::toString(
            $this->extension,
            $file,
            $this->encoding,
            $this->delimiter,
            $this->query
        ));
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withEncoding(string $encoding): self
    {
        return new self(self::toString(
            $this->extension,
            $this->file,
            $encoding,
            $this->delimiter,
            $this->query
        ));
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withDelimiter(string $delimiter): self
    {
        return new self(self::toString(
            $this->extension,
            $this->file,
            $this->encoding,
            $delimiter,
            $this->query
        ));
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withQuery(?string $query): self
    {
        return new self(self::toString(
            $this->extension,
            $this->file,
            $this->encoding,
            $this->delimiter,
            $query
        ));
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withExtension(?Enum\Format $extension): self
    {
        return new self(self::toString(
            $extension,
            $this->file,
            $this->encoding,
            $this->delimiter,
            $this->query
        ));
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public static function fromStream(Interface\Stream $stream): self
    {
        return new self($stream->provideSource());
    }

    public static function getRegexp(int $defaultPosition = 12): string
    {
        return sprintf(self::REGEXP, $defaultPosition);
    }

    public function __toString(): string
    {
        return self::toString(
            $this->extension,
            $this->file,
            $this->encoding,
            $this->delimiter,
            $this->query
        );
    }

    private static function toString(
        ?Enum\Format $extension,
        ?string $file,
        ?string $encoding,
        ?string $delimiter,
        ?string $query
    ): string {
        $fileQueryString = '';
        if ($extension !== null) {
            $fileQueryString .= "[{$extension->value}]";
        }

        if ($file !== null) {
            $fileQueryStringParts = [$file];
            $hasEncoding = $encoding !== null && $encoding !== '';
            $hasDefaultEncoding = $hasEncoding && strtolower($encoding) === 'utf-8';
            $hasDelimiter = $delimiter !== null && $delimiter !== '';
            $hasDefaultDelimiter = $hasDelimiter && strtolower($delimiter) === ',';

            $encodingSet = ($hasDelimiter && !$hasDefaultDelimiter) || ($hasEncoding && !$hasDefaultEncoding);
            if ($hasDelimiter && !$hasDefaultDelimiter) {
                $fileQueryStringParts[] = $hasEncoding ? $encoding : 'utf-8';
            } elseif ($hasEncoding && !$hasDefaultEncoding) {
                $fileQueryStringParts[] = $encoding;
            }

            if ($encodingSet && !$hasDefaultDelimiter) {
                $fileQueryStringParts[] = sprintf('"%s"', $delimiter);
            }

            $fileQueryString .= sprintf('(%s)', implode(', ', $fileQueryStringParts));
        }

        if ($query !== null) {
            if ($file !== null) {
                $fileQueryString .= '.';
            }
            $fileQueryString .= $query;
        }

        return $fileQueryString;
    }
}
