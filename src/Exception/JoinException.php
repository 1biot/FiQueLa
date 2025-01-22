<?php

namespace FQL\Exception;

class JoinException extends InvalidArgumentException
{
    public function __construct(string $message)
    {
        parent::__construct(sprintf('JOIN: %s', $message));
    }
}
