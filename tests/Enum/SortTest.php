<?php

namespace Enum;

use FQL\Enum\Sort;
use PHPUnit\Framework\TestCase;

class SortTest extends TestCase
{
    public function testCases(): void
    {
        $this->assertSame('asc', Sort::ASC->value);
        $this->assertSame('desc', Sort::DESC->value);
    }
}
