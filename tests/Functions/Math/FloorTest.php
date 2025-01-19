<?php

namespace Functions\Math;

use FQL\Exceptions\UnexpectedValueException;
use FQL\Functions\Math\Floor;
use PHPUnit\Framework\TestCase;

class FloorTest extends TestCase
{
    public function testFloor(): void
    {
        $floor = new Floor('price');
        $this->assertEquals(
            100,
            $floor([
                'price' => 100,
            ], [])
        );
    }

    public function testDecimalFloorMoreOrEqualThanFive(): void
    {
        $floor = new Floor('price');
        $this->assertEquals(
            100,
            $floor([
                'price' => 100.5,
            ], [])
        );
    }

    public function testDecimalFloorLessThanFive(): void
    {
        $floor = new Floor('price');
        $this->assertEquals(
            100,
            $floor([
                'price' => 100.4,
            ], [])
        );
    }

    public function testFloorWithStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $floor = new Floor('name');
        $floor([
            'name' => 'Product A',
        ], []);
    }

    public function testFloorWithEmptyValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $floor = new Floor('price');
        $floor([
            'price' => '',
        ], []);
    }

    public function testFloorWithNullValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $floor = new Floor('price');
        $floor([
            'price' => null,
        ], []);
    }

    public function testFloorWithZeroValue(): void
    {
        $floor = new Floor('price');
        $this->assertEquals(
            0,
            $floor([
                'price' => 0,
            ], [])
        );
    }

    public function testFloorWithNegativeValue(): void
    {
        $floor = new Floor('price');
        $this->assertEquals(
            -1,
            $floor([
                'price' => -0.1,
            ], [])
        );
    }
}
