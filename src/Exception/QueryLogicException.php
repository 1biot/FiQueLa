<?php

namespace FQL\Exception;

class QueryLogicException extends \LogicException implements SourceAwareException
{
    use SourceAwareTrait;
}
