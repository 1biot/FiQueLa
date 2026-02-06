<?php

namespace Functions\Math;

use PHPUnit\Framework\TestCase;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Math\Multiply;

class MultiplyTest extends TestCase
{
    public function testMultiply(): void
    {
        $mul = new Multiply('price', "2");
        $this->assertEquals(200, $mul(['price' => 100], []));
    }

    public function testMultiplyWithLiteral(): void
    {
        $mul = new Multiply('price', '"2"');
        $this->assertEquals(200, $mul(['price' => 100], []));
    }

    public function testMultiplyWithEmptyValue(): void
    {
        $mul = new Multiply('price', '"2"');
        $this->assertEquals(0, $mul(['price' => ''], []));
    }

    public function testMultiplyWithNullValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $mul = new Multiply('price', '"2"');
        $this->assertEquals(0, $mul(['price' => null], []));
    }

    public function testMultiplyWithFloat(): void
    {
        $mul = new Multiply('price', '"2.5"');
        $this->assertEquals(250, $mul(['price' => 100], []));
    }
}
