<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exceptions;

final class FileQuery
{
    private const REGEXP = '((\[(?<e>[a-z]{2,8})])?(\((?<fp>[\w,\s\.-\/\\\]+(\.\w{2,5}))\))?(?<q>[\w*\.\-\_]+)?)';

    public readonly ?Enum\Format $extension;
    public readonly ?string $file;
    public readonly ?string $query;

    /**
     * @throws Exceptions\FileQueryException
     * @throws Exceptions\InvalidFormatException
     */
    public function __construct(private readonly string $queryPath)
    {
        if (!preg_match('/^' . self::REGEXP . '$/', $this->queryPath, $matches)) {
            throw new Exceptions\FileQueryException('Invalid query path');
        }

        $extension = $matches['e'] === '' ? null : $matches['e'];
        if ($extension !== null) {
            $extension = Enum\Format::fromString(ltrim($extension, '.'));
        }

        $this->extension = $extension;
        $this->file = $matches['fp'] === '' ? null : $matches['fp'];
        $query = $matches['q'] ?? null;
        if ($query !== null) {
            $query = ltrim($query, '.');
        }
        $this->query = $query;
    }
}
