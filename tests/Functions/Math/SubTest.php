<?php

namespace Functions\Math;

use PHPUnit\Framework\TestCase;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Math\Sub;

class SubTest extends TestCase
{
    public function testSub(): void
    {
        $sub = new Sub('price', 5);
        $this->assertEquals(5, $sub(['price' => 10], []));
    }

    public function testSubWithLiteral(): void
    {
        $sub = new Sub('price', '"2"');
        $this->assertEquals(3, $sub(['price' => 5], []));
    }

    public function testSubWithEmptyValue(): void
    {
        $sub = new Sub('price', '"2"');
        $this->assertEquals(-2, $sub(['price' => ''], []));
    }

    public function testSubWithNullValue(): void
    {
        $sub = new Sub('price', '"2"');
        $this->assertEquals(-2, $sub(['price' => null], []));
    }

    public function testSubWithFloat(): void
    {
        $sub = new Sub('price', '"2.5"');
        $this->assertEquals(97.5, $sub(['price' => 100], []));
    }
}
