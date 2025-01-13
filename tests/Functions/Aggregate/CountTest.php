<?php

namespace Functions\Aggregate;

use PHPUnit\Framework\TestCase;
use FQL\Functions\Aggregate\Count;

class CountTest extends TestCase
{
    public function testCount(): void
    {
        $count = new Count('price');
        $this->assertEquals(
            4,
            $count(
                [
                    ['price' => 100],
                    ['price' => 200],
                    ['price' => 300],
                    ['price' => 400]
                ]
            )
        );
    }

    public function testCountWithStrings(): void
    {
        $count = new Count('name');
        $this->assertEquals(
            3,
            $count(
                [
                    ['name' => 'Product A'],
                    ['name' => 'Product B'],
                    ['name' => 'Product C']
                ]
            )
        );
    }

    public function testCountWithNumericStrings(): void
    {
        $count = new Count('numericPriceString');
        $this->assertEquals(
            6,
            $count([
                ['numericPriceString' => '100'],
                ['numericPriceString' => '200'],
                ['numericPriceString' => '300'],
                ['numericPriceString' => '400'],
                ['numericPriceString' => '500'],
                ['numericPriceString' => '600']
            ])
        );
    }

    public function testCountWithUndefinedField(): void
    {
        $count = new Count('numericPriceString');
        $this->assertEquals(
            5,
            $count([
                ['anotherNumericPriceString' => '300'],
                ['numericPriceString' => '100'],
                ['numericPriceString' => '200'],
                ['numericPriceString' => '300'],
                ['numericPriceString' => '400'],
                ['numericPriceString' => '500']
            ])
        );
    }

    public function testCountWithEmptyArray(): void
    {
        $count = new Count('price');
        $this->assertEquals(
            0,
            $count([])
        );
    }
}
