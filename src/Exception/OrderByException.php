<?php

namespace FQL\Exception;

class OrderByException extends InvalidArgumentException
{
    public function __construct(string $message)
    {
        parent::__construct(sprintf('ORDER BY: %s', $message));
    }
}
