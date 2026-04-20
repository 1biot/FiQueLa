<?php

namespace FQL\Sql\Builder;

use FQL\Exception;
use FQL\Query\FileQuery;
use FQL\Utils\FileQueryPathValidator;

/**
 * Thin wrapper around FileQueryPathValidator that applies a basePath (if set) to
 * every FileQuery produced by the AST. Mirrors the behaviour of the legacy
 * Sql::validateFileQueryPath().
 */
final class FileQueryResolver
{
    public function __construct(private readonly ?string $basePath = null)
    {
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public function resolve(FileQuery $fileQuery, bool $mustExist = true): FileQuery
    {
        if ($this->basePath === null) {
            return $fileQuery;
        }
        return FileQueryPathValidator::validate($fileQuery, $this->basePath, $mustExist);
    }

    public function getBasePath(): ?string
    {
        return $this->basePath;
    }
}
