<?php

namespace UQL\Functions\Core;

use UQL\Exceptions\UnexpectedValueException;
use UQL\Traits\Helpers\StringOperations;

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
