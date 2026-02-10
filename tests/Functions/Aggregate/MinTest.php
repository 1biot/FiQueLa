<?php

namespace Functions\Aggregate;

use PHPUnit\Framework\TestCase;
use FQL\Exception\InvalidArgumentException;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Aggregate\Min;

class MinTest extends TestCase
{
    /**
     * @throws UnexpectedValueException
     */
    public function testMax(): void
    {
        $min = new Min('price');
        $this->assertEquals(
            100,
            $min([
                ['price' => 100],
                ['price' => 200],
                ['price' => 300],
                ['price' => 400]
            ])
        );
    }

    public function testMaxWithStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Field "name" value is not numeric: Product A');

        $min = new Min('name');
        $min(
            [
                ['name' => 'Product A'],
                ['name' => 'Product B'],
                ['name' => 'Product C']
            ]
        );
    }

    public function testMaxWithUndefinedField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "undefinedField" not found');

        $min = new Min('undefinedField');
        $min([
            ['numericPriceString' => '100'],
            ['numericPriceString' => '200'],
            ['numericPriceString' => '300'],
            ['numericPriceString' => '400'],
            ['numericPriceString' => '500'],
            ['numericPriceString' => '600']
        ]);
    }

    public function testMaxWithNumericStrings(): void
    {
        $min = new Min('numericPriceString');
        $this->assertEquals(
            100,
            $min([
                ['numericPriceString' => '100'],
                ['numericPriceString' => '200'],
                ['numericPriceString' => '300'],
                ['numericPriceString' => '400'],
                ['numericPriceString' => '500'],
                ['numericPriceString' => '600']
            ])
        );
    }

    public function testMinDistinct(): void
    {
        $min = new Min('price', true);
        $this->assertEquals(
            100,
            $min([
                ['price' => 100],
                ['price' => 200],
                ['price' => 100],
                ['price' => 300]
            ])
        );
    }

    public function testMinIncrementalMatchesInvoke(): void
    {
        $min = new Min('price');
        $items = [
            ['price' => 100],
            ['price' => 200],
            ['price' => 300],
            ['price' => 400],
        ];

        $accumulator = $min->initAccumulator();
        foreach ($items as $item) {
            $accumulator = $min->accumulate($accumulator, $item);
        }

        $this->assertEquals($min($items), $min->finalize($accumulator));
    }

    public function testMinAccumulatorHandlesDuplicates(): void
    {
        $min = new Min('price', true);
        $accumulator = $min->initAccumulator();

        $accumulator = $min->accumulate($accumulator, ['price' => 10]);
        $accumulator = $min->accumulate($accumulator, ['price' => 10]);
        $accumulator = $min->accumulate($accumulator, ['price' => 5]);

        $this->assertSame(5, $min->finalize($accumulator));
    }

    public function testMinAccumulatorNonDistinctStartsFromNull(): void
    {
        $min = new Min('price');
        $accumulator = $min->initAccumulator();

        $accumulator = $min->accumulate($accumulator, ['price' => 7]);

        $this->assertSame(7, $min->finalize($accumulator));
    }

    public function testMinAccumulateRejectsNonNumeric(): void
    {
        $min = new Min('price');
        $accumulator = $min->initAccumulator();

        $this->expectException(UnexpectedValueException::class);

        $min->accumulate($accumulator, ['price' => 'bad']);
    }
}
