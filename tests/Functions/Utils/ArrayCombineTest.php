<?php

namespace Functions\Utils;

use FQL\Functions\Utils\ArrayCombine;
use PHPUnit\Framework\TestCase;

class ArrayCombineTest extends TestCase
{
    public function testCombine(): void
    {
        $combine = new ArrayCombine('keys', 'values');
        $this->assertEquals(
            ['a' => 1, 'b' => 2],
            $combine(
                [
                    'keys' => ['a', 'b'],
                    'values' => [1, 2],
                ],
                []
            )
        );
    }

    public function testCombineWithAssociativeKeys(): void
    {
        $combine = new ArrayCombine('keys', 'values');
        $this->assertEquals(
            ['x' => 1, 'y' => 2],
            $combine(
                [
                    'keys' => ['a' => 'x', 'b' => 'y'],
                    'values' => [1, 2],
                ],
                []
            )
        );
    }

    public function testInvalidKeys(): void
    {
        $combine = new ArrayCombine('keys', 'values');
        $this->assertEquals(
            null,
            $combine(
                [
                    'keys' => 'invalid',
                    'values' => [1, 2],
                ],
                []
            )
        );
    }
}
