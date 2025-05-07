<?php

namespace FQL\Functions\Utils;

class SelectIfNull extends SelectIf
{
    public function __construct(string $field, string $trueStatement)
    {
        parent::__construct(sprintf('%s IS NULL', $field), $trueStatement, $field);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, %s)',
            'IFNULL',
            $this->fields[0],
            $this->trueStatement
        );
    }
}
