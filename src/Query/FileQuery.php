<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception;

final class FileQuery implements \Stringable
{
    private const REGEXP = '((?<fs>(\[(?<e>[a-zA-Z]{2,8})])?(\((?<fp>[\w,\s\.\-\/]+(\.\w{2,5})?)\)))?(?<q>[\w*\.\-\_]+)?)';

    public readonly ?Enum\Format $extension;
    public readonly ?string $file;
    public readonly ?string $query;

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function __construct(private readonly string $queryPath)
    {
        if (!preg_match('/^' . self::REGEXP . '$/', $this->queryPath, $matches)) {
            throw new Exception\FileQueryException('Invalid query path');
        }

        $extension = $matches['e'] ?? null;
        if ($extension === null || $extension === '') {
            $extension = null;
        }
        $this->extension = $extension !== null ? Enum\Format::fromExtension($extension) : null;

        $filePath = $matches['fp'] ?? null;
        if ($filePath === null || $filePath === '') {
            $filePath = null;
        }
        $this->file = $filePath ?? null;

        $query = $matches['q'] ?? null;
        if ($query !== null) {
            $query = ltrim($query, '.');
        }
        $this->query = $query;
    }

    public static function getRegexp(): string
    {
        return self::REGEXP;
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
