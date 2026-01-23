<?php

namespace Enum;

use FQL\Enum\Fulltext;
use PHPUnit\Framework\TestCase;

class FulltextTest extends TestCase
{
    public function testCalculateBoolean(): void
    {
        $score = Fulltext::BOOLEAN->calculate('hello world', ['+hello', '-missing']);
        $this->assertSame(2.0, $score);
    }

    public function testCalculateNatural(): void
    {
        $score = Fulltext::NATURAL->calculate('hello world', ['hello', 'world']);
        $this->assertSame(7.0, $score);
    }
}
