<?php

namespace FQL\Exception;

/**
 * A source or target file that exists but cannot be opened. Rule `file-unreadable`.
 */
class UnableOpenFileException extends \Exception implements SourceAwareException
{
    use SourceAwareTrait;

    public function getRule(): ?string
    {
        return 'file-unreadable';
    }
}
