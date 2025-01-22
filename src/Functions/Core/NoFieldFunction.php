<?php

namespace FQL\Functions\Core;

use FQL\Exception\UnexpectedValueException;
use FQL\Interface\InvokableNoField;
use FQL\Traits\Helpers\StringOperations;

abstract class NoFieldFunction implements InvokableNoField, \Stringable
{
    use StringOperations;

    /**
     * @throws UnexpectedValueException
     */
    public function getName(): string
    {
        $array = preg_split('/\\\/', $this::class);
        if ($array === false) {
            throw new UnexpectedValueException('Cannot split class name');
        }

        $functionName = end($array);
        return $this->camelCaseToUpperSnakeCase($functionName === false ? '' : $functionName);
    }
}
