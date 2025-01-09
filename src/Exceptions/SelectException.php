<?php

namespace UQL\Exceptions;

class SelectException extends InvalidArgumentException
{
    public function __construct(string $message)
    {
        parent::__construct(sprintf('SELECT: %s', $message));
    }
}
