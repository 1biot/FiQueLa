<?php

namespace FQL\Functions\String;

use FQL\Exception\UnexpectedValueException;

final class Concat extends ConcatWS
{
    public function __construct(string ...$fields)
    {
        parent::__construct('', ...$fields);
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            $this->getName(),
            implode(', ', $this->fields)
        );
    }
}
