<?php

namespace Functions\Aggregate;

use PHPUnit\Framework\TestCase;
use FQL\Functions\Aggregate\Count;
use FQL\Exception\InvalidArgumentException;

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

    public function testCountDistinct(): void
    {
        $count = new Count('name', true);
        $this->assertEquals(
            2,
            $count([
                ['name' => 'Product A'],
                ['name' => 'Product A'],
                ['name' => null],
                ['name' => 'Product B'],
                ['name' => 'Product B']
            ])
        );
    }

    public function testCountDistinctWithSelectAllThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DISTINCT is not supported with COUNT(*)');

        new Count(null, true);
    }

    public function testCountIncrementalMatchesInvoke(): void
    {
        $count = new Count();
        $items = [
            ['price' => 100],
            ['price' => 200],
            ['price' => 300],
            ['price' => 400],
        ];

        $accumulator = $count->initAccumulator();
        foreach ($items as $item) {
            $accumulator = $count->accumulate($accumulator, $item);
        }

        $this->assertEquals($count($items), $count->finalize($accumulator));
    }

    public function testCountDistinctIncrementalMatchesInvoke(): void
    {
        $count = new Count('price', true);
        $items = [
            ['price' => 100],
            ['price' => 100],
            ['price' => 200],
            ['price' => 300],
            ['price' => 300],
        ];

        $accumulator = $count->initAccumulator();
        foreach ($items as $item) {
            $accumulator = $count->accumulate($accumulator, $item);
        }

        $this->assertEquals($count($items), $count->finalize($accumulator));
    }
}
