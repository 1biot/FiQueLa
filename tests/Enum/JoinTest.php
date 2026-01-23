<?php

namespace Enum;

use FQL\Enum\Join;
use PHPUnit\Framework\TestCase;

class JoinTest extends TestCase
{
    public function testCases(): void
    {
        $this->assertSame('INNER JOIN', Join::INNER->value);
        $this->assertSame('LEFT JOIN', Join::LEFT->value);
        $this->assertSame('RIGHT JOIN', Join::RIGHT->value);
        $this->assertSame('FULL JOIN', Join::FULL->value);
    }
}
