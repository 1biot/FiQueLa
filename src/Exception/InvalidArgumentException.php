<?php

namespace FQL\Exception;

class InvalidArgumentException extends \InvalidArgumentException implements SourceAwareException
{
    use SourceAwareTrait;
}
