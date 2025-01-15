<?php

namespace FQL\Exceptions;

class SortException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null
    ) {
        parent::__construct(sprintf('ORDER BY: %s', $message), previous: $previous);
    }
}
