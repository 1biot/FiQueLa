<?php

namespace Functions\Math;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Math\Round;
use PHPUnit\Framework\TestCase;

class RoundTest extends TestCase
{
    public function testRound(): void
    {
        $round = new Round('price');
        $this->assertEquals(
            100,
            $round([
                'price' => 100,
            ], [])
        );
        $this->assertEquals(
            100,
            $round([
                'price' => 100.1,
            ], [])
        );
        $this->assertEquals(
            101,
            $round([
                'price' => 100.5,
            ], [])
        );
    }

    public function testRoundWithStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $round = new Round('name');
        $round([
            'name' => 'Product A',
        ], []);
    }

    public function testRoundWithEmptyValue(): void
    {
        $round = new Round('price');
        $this->assertEquals(
            0,
            $round([
                'price' => '',
            ], [])
        );
    }

    public function testRoundWithNullValue(): void
    {
        $round = new Round('price');
        $this->assertEquals(
            0,
            $round([
                'price' => null,
            ], [])
        );
    }

    public function testRoundWithZeroValue(): void
    {
        $round = new Round('price');
        $this->assertEquals(
            0,
            $round([
                'price' => 0,
            ], [])
        );
    }

    public function testRoundWithNegativeValue(): void
    {
        $round = new Round('price');
        $this->assertEquals(
            -101,
            $round([
                'price' => -100.5,
            ], [])
        );
        $this->assertEquals(
            -100,
            $round([
                'price' => -100.4,
            ], [])
        );
    }
}
