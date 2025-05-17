<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\NoFieldFunction;

class CurrentTimestamp extends NoFieldFunction
{
    public function __invoke(): int
    {
        return time();
    }

    public function __toString(): string
    {
        return sprintf('%s()', $this->getName());
    }
}
