<?php

namespace Functions\String;

use FQL\Functions\String\Concat;
use PHPUnit\Framework\TestCase;

class ConcatTest extends TestCase
{
    public function testConstruct(): void
    {
        $concat = new Concat('a', 'b', 'c');
        $this->assertSame('CONCAT(a, b, c)', (string) $concat);
    }

    public function testConstructEmpty(): void
    {
        $concat = new Concat();
        $this->assertSame('CONCAT()', (string) $concat);
    }

    public function testInvoke(): void
    {
        $concat = new Concat('e', 'f', 'g');
        $this->assertSame('efg', $concat->__invoke(['a' => 'a', 'b' => 'b', 'c' => 'c'], []));

        $concat = new Concat('c', 'b', 'a');
        $this->assertSame('cba', $concat->__invoke(['a' => 'a', 'b' => 'b', 'c' => 'c'], []));
    }
}
