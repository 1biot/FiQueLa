<?php

namespace Functions\Aggregate;

use PHPUnit\Framework\TestCase;
use FQL\Exceptions\UnexpectedValueException;
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
}
