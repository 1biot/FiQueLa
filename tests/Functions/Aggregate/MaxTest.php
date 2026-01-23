<?php

namespace Functions\Aggregate;

use PHPUnit\Framework\TestCase;
use FQL\Exception\InvalidArgumentException;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Aggregate\Max;

class MaxTest extends TestCase
{
    /**
     * @throws UnexpectedValueException
     */
    public function testMax(): void
    {
        $max = new Max('price');
        $this->assertEquals(
            400,
            $max([
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

        $max = new Max('name');
        $max(
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

        $max = new Max('undefinedField');
        $max([
            ['numericPriceString' => '100'],
            ['numericPriceString' => '200'],
            ['numericPriceString' => '300'],
            ['numericPriceString' => '400'],
            ['numericPriceString' => '500'],
            ['numericPriceString' => '600']
        ]);
    }

    /**
     * @throws UnexpectedValueException
     */
    public function testMaxWithNumericStrings(): void
    {
        $max = new Max('numericPriceString');
        $this->assertEquals(
            600,
            $max([
                ['numericPriceString' => '100'],
                ['numericPriceString' => '200'],
                ['numericPriceString' => '300'],
                ['numericPriceString' => '400'],
                ['numericPriceString' => '500'],
                ['numericPriceString' => '600']
            ])
        );
    }

    public function testMaxIncrementalMatchesInvoke(): void
    {
        $max = new Max('price');
        $items = [
            ['price' => 100],
            ['price' => 200],
            ['price' => 300],
            ['price' => 400],
        ];

        $accumulator = $max->initAccumulator();
        foreach ($items as $item) {
            $accumulator = $max->accumulate($accumulator, $item);
        }

        $this->assertEquals($max($items), $max->finalize($accumulator));
    }
}
