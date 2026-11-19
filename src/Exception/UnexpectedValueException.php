<?php

namespace FQL\Exception;

class UnexpectedValueException extends \UnexpectedValueException implements SourceAwareException
{
    use SourceAwareTrait;
}
