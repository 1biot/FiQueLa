<?php

namespace Functions\Math;

use PHPUnit\Framework\TestCase;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Math\Divide;

class DivideTest extends TestCase
{
    public function testDivide(): void
    {
        $div = new Divide('price', '"2"');
        $this->assertEquals(50, $div(['price' => 100], []));
    }

    public function testDivideMultiple(): void
    {
        $div = new Divide('"100"', '"2"', '"5"');
        $this->assertEquals(10, $div([ ], []));
    }

    public function testDivideByZeroRuntime(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $div = new Divide('price', '"0"');
        $div(['price' => 10], []);
    }
}
