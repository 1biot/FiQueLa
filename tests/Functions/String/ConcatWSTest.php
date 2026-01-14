<?php

namespace Functions\String;

use FQL\Functions\String\ConcatWS;
use PHPUnit\Framework\TestCase;

class ConcatWSTest extends TestCase
{
    public function testInvoke(): void
    {
        $concat = new ConcatWS('-', 'first', 'second');
        $this->assertSame('a-b', $concat(['first' => 'a', 'second' => 'b'], []));
    }

    public function testInvokeWithLiteralAndWhitespace(): void
    {
        $concat = new ConcatWS('|', '"hello"', ' ', 'name');
        $this->assertSame('hello| |world', $concat(['name' => 'world'], []));
    }

    public function testToString(): void
    {
        $concat = new ConcatWS('-', 'first', 'second');
        $this->assertSame('CONCAT_WS("-", first, second)', (string) $concat);
    }
}
