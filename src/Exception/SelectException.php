<?php

namespace FQL\Exception;

class SelectException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null
    ) {
        parent::__construct(sprintf('SELECT: %s', $message), previous: $previous);
    }
}
