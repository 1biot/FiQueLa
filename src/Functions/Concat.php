<?php

namespace UQL\Functions;

final class Concat extends ConcatWS
{
    public function __construct(string ...$fields)
    {
        parent::__construct('', ...$fields);
    }

    public function __toString()
    {
        return sprintf(
            '%s(%s)',
            $this->getName(),
            implode(', ', $this->fields)
        );
    }
}
