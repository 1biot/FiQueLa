<?php

namespace Functions\Utils;

use FQL\Functions\Utils\ArrayMerge;
use PHPUnit\Framework\TestCase;

class ArrayMergeTest extends TestCase
{
    public function testMerge(): void
    {
        $merge = new ArrayMerge('firstArray', 'secondArray');
        $this->assertEquals(
            ['a', 'b', 1, 2],
            $merge(
                [
                    'firstArray' => ['a', 'b'],
                    'secondArray' => [1, 2],
                ],
                []
            )
        );
    }

    public function testMergeWithAssociativeKeys(): void
    {
        $merge = new ArrayMerge('firstArray', 'secondArray');
        $this->assertEquals(
            [1, 2, 'a' => 'x', 'b' => 'y'],
            $merge(
                [
                    'firstArray' => ['a' => 'x', 'b' => 'y'],
                    'secondArray' => [1, 2],
                ],
                []
            )
        );
    }

    public function testInvalidKeys(): void
    {
        $merge = new ArrayMerge('firstArray', 'secondArray');
        $this->assertEquals(
            null,
            $merge(
                [
                    'firstArray' => 'notArray',
                    'secondArray' => [1, 2],
                ],
                []
            )
        );
    }

    public function testInvalidValues(): void
    {
        $merge = new ArrayMerge('firstArray', 'secondArray');
        $this->assertEquals(
            null,
            $merge(
                [
                    'firstArray' => ['a', 'b'],
                    'secondArray' => 'notArray',
                ],
                []
            )
        );
    }
}
