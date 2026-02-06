<?php

namespace Functions\Math;

use PHPUnit\Framework\TestCase;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Math\Add;

class AddTest extends TestCase
{
    public function testAdd(): void
    {
        $add = new Add('price', "2");
        $this->assertEquals(102, $add(['price' => 100], []));
    }

    public function testAddWithStrings(): void
    {
        $add = new Add('price', '"2"');
        $this->assertEquals(5, $add(['price' => 3], []));
    }

    public function testAddWithEmptyAndOtherValue(): void
    {
        $add = new Add('price', '"2"');
        $this->assertEquals(2, $add(['price' => ''], []));
    }

    public function testAddWithNullValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $add = new Add('price', '"2"');
        $this->assertEquals(2, $add(['price' => null], []));
    }

    public function testAddWithFloat(): void
    {
        $add = new Add('price', '"0.5"');
        $this->assertEquals(100.5, $add(['price' => 100], []));
    }
}
