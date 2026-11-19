<?php

namespace FQL\Exception;

/**
 * A file a query reads from does not exist under the base path (the query was compiled
 * with a `basePath`). Rule `file-not-found`, params `['path' => …, 'basePath' => …]`,
 * where `path` is the path as written in the query.
 *
 * The message stays `Invalid path of file` as before 3.3.0; {@see getReason()} names
 * the file.
 */
class PathNotFoundException extends InvalidFormatException
{
    public static function create(string $path, string $basePath): self
    {
        $exception = new self('Invalid path of file');
        $exception->describeSource(
            'file-not-found',
            ['path' => $path, 'basePath' => $basePath],
            sprintf('File "%s" does not exist', $path)
        );
        return $exception;
    }

    public function getRule(): ?string
    {
        return 'file-not-found';
    }
}
