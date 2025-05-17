<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\NoFieldFunction;

class Now extends NoFieldFunction
{
    public function __construct(private readonly bool $numeric = false)
    {
    }

    public function __toString(): string
    {
        return sprintf('%s()', $this->getName());
    }

    public function __invoke(): string|int
    {
        $today = new \DateTime();
        return $this->numeric
            ? (int) $today->format('YmdHis')
            : $today->format('Y-m-d H:i:s');
    }
}
