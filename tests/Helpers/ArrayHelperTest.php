<?php

namespace UQL\Helpers;

use PHPUnit\Framework\TestCase;

class ArrayHelperTest extends TestCase
{
    private array $testArray = [
        'a' => [
            'b' => [
                'c' => 'value'
            ],
            'd' => 'value2',
            'e' => [
                ['x' => 1, 'y' => 2, 'z' => 3],
                ['x' => 2, 'y' => 3, 'z' => 4],
                ['x' => 3, 'y' => 4, 'z' => 5],
            ]
        ]
    ];

    public function testGetNestedValue(): void
    {
        $this->assertEquals('value', ArrayHelper::getNestedValue($this->testArray, 'a.b.c'));
        $this->assertEquals('value2', ArrayHelper::getNestedValue($this->testArray, 'a.d'));

        $result = ArrayHelper::getNestedValue($this->testArray, 'a.e[]->x');
        $this->assertEquals(1, $result[0]);
        $this->assertEquals(2, $result[1]);
        $this->assertEquals(3, $result[2]);

        $result = ArrayHelper::getNestedValue($this->testArray, 'a.e[]->y');
        $this->assertEquals(2, $result[0]);
        $this->assertEquals(3, $result[1]);
        $this->assertEquals(4, $result[2]);

        $result = ArrayHelper::getNestedValue($this->testArray, 'a.e[]->z');
        $this->assertEquals(3, $result[0]);
        $this->assertEquals(4, $result[1]);
        $this->assertEquals(5, $result[2]);

        $this->assertEquals(3, ArrayHelper::getNestedValue($this->testArray, 'a.e.0->z'));
        $this->assertEquals(4, ArrayHelper::getNestedValue($this->testArray, 'a.e.1->z'));
        $this->assertEquals(5, ArrayHelper::getNestedValue($this->testArray, 'a.e.2->z'));
    }
}
