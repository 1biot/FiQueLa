<?php

namespace Functions;

use PHPUnit\Framework\TestCase;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions\Ceil;

class CeilTest extends TestCase
{
    public function testCeil(): void
    {
        $ceil = new Ceil('price');
        $this->assertEquals(
            100,
            $ceil([
                'price' => 100,
            ], [])
        );
    }

    public function testCeilWithStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $ceil = new Ceil('name');
        $ceil([
            'name' => 'Product A',
        ], []);
    }

    public function testCeilWithEmptyValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $ceil = new Ceil('price');
        $ceil([
            'price' => '',
        ], []);
    }

    public function testCeilWithNullValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $ceil = new Ceil('price');
        $ceil([
            'price' => null,
        ], []);
    }

    public function testCeilWithZeroValue(): void
    {
        $ceil = new Ceil('price');
        $this->assertEquals(
            0,
            $ceil([
                'price' => 0,
            ], [])
        );
    }

    public function testCeilWithNegativeValue(): void
    {
        $ceil = new Ceil('price');
        $this->assertEquals(
            -100,
            $ceil([
                'price' => -100,
            ], [])
        );
    }

    public function testCeilWithFloatValue(): void
    {
        $ceil = new Ceil('price');
        $this->assertEquals(
            100,
            $ceil([
                'price' => 99.99,
            ], [])
        );
    }

    public function testCeilWithFloatValue2(): void
    {
        $ceil = new Ceil('price');
        $this->assertEquals(
            100,
            $ceil([
                'price' => 99.01,
            ], [])
        );
    }

    public function testCeilWithNegativeFloatValue(): void
    {
        $ceil = new Ceil('price');
        $this->assertEquals(
            -99,
            $ceil([
                'price' => -99.1,
            ], [])
        );
    }

    public function testCeilWithNegativeFloatValue2(): void
    {
        $ceil = new Ceil('price');
        $this->assertEquals(
            -99,
            $ceil([
                'price' => -99.99,
            ], [])
        );
    }

    public function testCeilChain(): void
    {
        $ceil = new Ceil('price');
        $this->assertEquals(
            54,
            $ceil([
                'oldPrice' => 99.01,
            ], [
                'price' => 53.99,
            ])
        );
    }
}
