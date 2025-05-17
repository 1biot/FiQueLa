<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\NoFieldFunction;

class CurrentTime extends NoFieldFunction
{
    public function __construct(private readonly bool $numeric = false)
    {
    }

    public function __toString(): string
    {
        return sprintf('CURTIME(%s)', $this->numeric ? 'true' : 'false');
    }

    public function __invoke(): string|int
    {
        $now = new \DateTime();
        return $this->numeric
            ? (int) $now->format('His')
            : $now->format('H:i:s');
    }
}
