<?php

namespace FQL\Exception;

class Exception extends \Exception implements SourceAwareException
{
    use SourceAwareTrait;
}
