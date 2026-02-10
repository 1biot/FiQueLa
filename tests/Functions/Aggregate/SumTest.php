<?php

namespace Functions\Aggregate;

use PHPUnit\Framework\TestCase;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Aggregate\Sum;

class SumTest extends TestCase
{
    public function testSum(): void
    {
        $sum = new Sum('price');
        $this->assertEquals(
            1500,
            $sum(
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

    public function testSumWithStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $sum = new Sum('name');
        $sum(
            [
                ['name' => 'Product A'],
                ['name' => 'Product B'],
                ['name' => 'Product C'],
                ['name' => 'Product D'],
                ['name' => 'Product E']
            ]
        );
    }

    public function testSumWithNumericStrings(): void
    {
        $sum = new Sum('numericPriceString');
        $this->assertEquals(
            1500,
            $sum([
                ['numericPriceString' => '100'],
                ['numericPriceString' => '200'],
                ['numericPriceString' => '300'],
                ['numericPriceString' => '400'],
                ['numericPriceString' => '500']
            ])
        );
    }

    public function testSumWithEmptyArray(): void
    {
        $sum = new Sum('price');
        $this->assertEquals(0, $sum([]));
    }

    public function testSumDistinct(): void
    {
        $sum = new Sum('price', true);
        $this->assertEquals(
            600,
            $sum([
                ['price' => 100],
                ['price' => 200],
                ['price' => 200],
                ['price' => 300],
                ['price' => 300]
            ])
        );
    }

    public function testSumIncrementalMatchesInvoke(): void
    {
        $sum = new Sum('price');
        $items = [
            ['price' => 100],
            ['price' => 200],
            ['price' => 300],
            ['price' => 400],
            ['price' => 500],
        ];

        $accumulator = $sum->initAccumulator();
        foreach ($items as $item) {
            $accumulator = $sum->accumulate($accumulator, $item);
        }

        $this->assertEquals($sum($items), $sum->finalize($accumulator));
    }

    public function testSumDistinctIncrementalMatchesInvoke(): void
    {
        $sum = new Sum('price', true);
        $items = [
            ['price' => 100],
            ['price' => 200],
            ['price' => 200],
            ['price' => 300],
            ['price' => 300],
        ];

        $accumulator = $sum->initAccumulator();
        foreach ($items as $item) {
            $accumulator = $sum->accumulate($accumulator, $item);
        }

        $this->assertEquals($sum($items), $sum->finalize($accumulator));
    }

    public function testSumAccumulatorSkipsDuplicateDistinct(): void
    {
        $sum = new Sum('price', true);
        $accumulator = $sum->initAccumulator();

        $accumulator = $sum->accumulate($accumulator, ['price' => 100]);
        $accumulator = $sum->accumulate($accumulator, ['price' => 100]);

        $this->assertSame(100, $sum->finalize($accumulator));
    }

    public function testSumAccumulatorAddsWithoutDistinct(): void
    {
        $sum = new Sum('price');
        $accumulator = $sum->initAccumulator();

        $accumulator = $sum->accumulate($accumulator, ['price' => 2]);

        $this->assertSame(2, $sum->finalize($accumulator));
    }

    public function testSumTreatsEmptyStringAsZero(): void
    {
        $sum = new Sum('price');

        $this->assertSame(0, $sum([
            ['price' => ''],
        ]));
    }

    public function testSumAccumulateTreatsEmptyStringAsZero(): void
    {
        $sum = new Sum('price');
        $accumulator = $sum->initAccumulator();

        $accumulator = $sum->accumulate($accumulator, ['price' => '']);

        $this->assertSame(0, $sum->finalize($accumulator));
    }
}
