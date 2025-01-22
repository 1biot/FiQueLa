<?php

namespace Functions\Aggregate;

use PHPUnit\Framework\TestCase;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Aggregate\Avg;

class AvgTest extends TestCase
{
    public function testAvg(): void
    {
        $avg = new Avg('price');
        $this->assertEquals(
            300,
            $avg(
                [
                    ['price' => 100],
                    ['price' => 200],
                    ['price' => 300],
                    ['price' => 400],
                    ['price' => 500]
                ]
            )
        );
    }

    public function testAvgWithStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $avg = new Avg('name');
        $avg(
            [
                ['name' => 'Product A'],
                ['name' => 'Product B'],
                ['name' => 'Product C'],
                ['name' => 'Product D'],
                ['name' => 'Product E']
            ]
        );
    }

    public function testAvgWithNumericStrings(): void
    {
        $avg = new Avg('numericPriceString');
        $this->assertEquals(
            300,
            $avg([
                ['numericPriceString' => '100'],
                ['numericPriceString' => '200'],
                ['numericPriceString' => '300'],
                ['numericPriceString' => '400'],
                ['numericPriceString' => '500']
            ])
        );
    }

    public function testAvgWithEmptyArray(): void
    {
        $avg = new Avg('price');
        $this->assertEquals(0, $avg([]));
    }

    public function testAvgWithZeroValues(): void
    {
        $avg = new Avg('price');
        $this->assertEquals(0, $avg([
            ['price' => 0],
            ['price' => 0],
            ['price' => 0]
        ]));
    }

    public function testAvgWithNegativeValues(): void
    {
        $avg = new Avg('price');
        $this->assertEquals(-200, $avg([
            ['price' => -100],
            ['price' => -200],
            ['price' => -300]
        ]));
    }

    public function testAvgWithFloatValues(): void
    {
        $avg = new Avg('price');
        $this->assertEquals(1.5, $avg([
            ['price' => 1],
            ['price' => 2],
        ]));
    }

    public function testAvgWithFloatValues2(): void
    {
        $avg = new Avg('price');
        $this->assertEquals(1.5, $avg([
            ['price' => 1.0],
            ['price' => 2.0],
        ]));
    }
}
