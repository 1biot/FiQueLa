<?php

namespace Functions\Utils;

use FQL\Functions\Utils\CoalesceNotEmpty;
use PHPUnit\Framework\TestCase;

class CoalesceNotEmptyTest extends TestCase
{
    public function testCoalesceNotEmpty(): void
    {
        $coalesceNotEmpty = new CoalesceNotEmpty('price', 'stock');
        $this->assertEquals(
            100,
            $coalesceNotEmpty([
                'price' => 100,
                'stock' => 0,
            ], [])
        );
    }

    public function testCoalesceNotEmptyWithStrings(): void
    {
        $coalesceNotEmpty = new CoalesceNotEmpty('stock', 'name');
        $this->assertEquals(
            'Product A',
            $coalesceNotEmpty([
                'stock' => 0,
                'name' => 'Product A',
            ], [])
        );
    }

    public function testCoalesceNotEmptyWithEmptyValue(): void
    {
        $coalesceNotEmpty = new CoalesceNotEmpty('price', 'stock');
        $this->assertEquals(
            '',
            $coalesceNotEmpty([
                'price' => '',
                'stock' => 0,
            ], [])
        );
    }

    public function testCoalesceNotEmptyWithNullValue(): void
    {
        $coalesceNotEmpty = new CoalesceNotEmpty('price', 'stock');
        $this->assertEquals(
            '',
            $coalesceNotEmpty([
                'price' => null,
                'stock' => 0,
            ], [])
        );
    }

    public function testCoalesceNotEmptyWithZeroValue(): void
    {
        $coalesceNotEmpty = new CoalesceNotEmpty('price', 'stock');
        $this->assertEquals(
            '',
            $coalesceNotEmpty([
                'price' => 0,
                'stock' => 0,
            ], [])
        );
    }

    public function testCoalesceNotEmptyWithNegativeValue(): void
    {
        $coalesceNotEmpty = new CoalesceNotEmpty('price', 'stock');
        $this->assertEquals(
            -1,
            $coalesceNotEmpty([
                'price' => -1,
                'stock' => 0,
            ], [])
        );
    }
}
