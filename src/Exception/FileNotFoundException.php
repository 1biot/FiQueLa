<?php

namespace FQL\Exception;

/**
 * A source file that does not exist or cannot be read when its stream is opened.
 * Rule `file-not-found`, params `['path' => …]` (the path the stream was opened with)
 * when known.
 */
final class FileNotFoundException extends Exception
{
    public static function forPath(string $path, string $message = 'File not found or not readable.'): self
    {
        $exception = new self($message);
        $exception->describeSource(
            'file-not-found',
            ['path' => $path],
            sprintf('File "%s" not found or not readable', $path)
        );
        return $exception;
    }

    public function getRule(): ?string
    {
        return 'file-not-found';
    }
}
