<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception;
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
        $fileQueryString = '';
        if ($this->extension !== null) {
            $fileQueryString .= "[{$this->extension->value}]";
        }

        if ($this->file !== null) {
            $fileQueryString .= "({$this->file})";
        }

        if ($this->query !== null) {
            if ($this->file !== null) {
                $fileQueryString .= '.';
            }
            $fileQueryString .= $this->query;
        }

        return $fileQueryString;
    }
}
