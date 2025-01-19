<?php

namespace Functions\Math;

use FQL\Exceptions\UnexpectedValueException;
use FQL\Functions\Math\Mod;
use PHPUnit\Framework\TestCase;

class ModTest extends TestCase
{
    public function testMod(): void
    {
        $mod = new Mod('price', 2);
        $this->assertEquals(
            0,
            $mod([
                'price' => 100,
            ], [])
        );
    }

    public function testModWithStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $mod = new Mod('name', 2);
        $mod([
            'name' => 'Product A',
        ], []);
    }

    public function testModWithEmptyValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $mod = new Mod('price', 2);
        $mod([
            'price' => '',
        ], []);
    }

    public function testModWithNullValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $mod = new Mod('price', 2);
        $mod([
            'price' => null,
        ], []);
    }

    public function testModWithZeroValue(): void
    {
        $mod = new Mod('price', 2);
        $this->assertEquals(
            0,
            $mod([
                'price' => 0,
            ], [])
        );
    }

    public function testModWithNegativeValue(): void
    {
        $mod = new Mod('price', 2);
        $this->assertEquals(
            -1,
            $mod([
                'price' => -1,
            ], [])
        );
    }
}
