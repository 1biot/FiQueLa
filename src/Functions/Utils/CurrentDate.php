<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\NoFieldFunction;

class CurrentDate extends NoFieldFunction
{
    public function __construct(private readonly bool $numeric = false)
    {
    }

    public function __invoke(): string|int
    {
        $today = new \DateTime();
        return $this->numeric
            ? (int) $today->format('Ymd')
            : $today->format('Y-m-d');
    }

    public function __toString(): string
    {
        return sprintf('CURDATE(%s)', $this->numeric ? 'true' : 'false');
    }
}
