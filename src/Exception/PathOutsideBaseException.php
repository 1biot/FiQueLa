<?php

namespace FQL\Exception;

/**
 * A file a query reads from or writes to lies outside the base path the query was
 * compiled with. Rule `file-outside-base`, params `['path' => …, 'basePath' => …]`,
 * where `path` is the path as written in the query.
 *
 * The message stays `Invalid path of file` as before 3.3.0; {@see getReason()} names
 * the file.
 */
class PathOutsideBaseException extends InvalidFormatException
{
    public static function create(string $path, string $basePath): self
    {
        $exception = new self('Invalid path of file');
        $exception->describeSource(
            'file-outside-base',
            ['path' => $path, 'basePath' => $basePath],
            sprintf('File "%s" is outside the base path', $path)
        );
        return $exception;
    }

    public function getRule(): ?string
    {
        return 'file-outside-base';
    }
}
