<?php

namespace FQL\Functions\Utils;

class SelectIsNull extends SelectIf
{
    public function __construct(private readonly string $field)
    {
        parent::__construct(sprintf('%s IS NULL', $field), 'true', 'false');
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            'ISNULL',
            $this->field
        );
    }
}
