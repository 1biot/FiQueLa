<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception;

final class FileQuery
{
    private const REGEXP = '((\[(?<e>[a-z]{2,8})])?(\((?<fp>[\w,\s\.-\/\\\]+(\.\w{2,5}))\))?(?<q>[\w*\.\-\_]+)?)';

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
        $this->extension = $extension !== null ? Enum\Format::fromString($extension) : null;

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
}
