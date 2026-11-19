<?php

namespace FQL\Exception;

/**
 * The target file of `INTO` already exists (FiQueLa never overwrites files).
 * Rule `file-exists`, params `['path' => …]` (the resolved target path) when known.
 */
class FileAlreadyExistsException extends \RuntimeException implements SourceAwareException
{
    use SourceAwareTrait;

    public static function forPath(string $path): self
    {
        $exception = new self(sprintf('File "%s" already exists', $path));
        $exception->describeSource('file-exists', ['path' => $path]);
        return $exception;
    }

    public function getRule(): ?string
    {
        return 'file-exists';
    }
}
