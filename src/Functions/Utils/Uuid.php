<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\NoFieldFunction;

class Uuid extends NoFieldFunction
{
    public function __invoke(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public function __toString(): string
    {
        return sprintf('%s()', $this->getName());
    }
}
