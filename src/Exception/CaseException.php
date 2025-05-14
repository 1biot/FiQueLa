<?php

namespace FQL\Exception;

class CaseException extends InvalidArgumentException
{
    public function __construct(string $message)
    {
        parent::__construct(sprintf('CASE: %s', $message));
    }
}
