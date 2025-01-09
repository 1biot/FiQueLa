<?php

namespace Traits\Helpers;

use PHPUnit\Framework\TestCase;
use UQL\Helpers\ArrayHelper;
use UQL\Traits\Helpers\NestedArrayAccessor;

class NestedArrayAccessorTest extends TestCase
{
    use NestedArrayAccessor;

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
        $this->assertEquals('value', $this->accessNestedValue($this->testArray, 'a.b.c'));
        $this->assertEquals('value2', $this->accessNestedValue($this->testArray, 'a.d'));

        $result = $this->accessNestedValue($this->testArray, 'a.e[]->x');
        $this->assertEquals(1, $result[0]);
        $this->assertEquals(2, $result[1]);
        $this->assertEquals(3, $result[2]);

        $result = $this->accessNestedValue($this->testArray, 'a.e[]->y');
        $this->assertEquals(2, $result[0]);
        $this->assertEquals(3, $result[1]);
        $this->assertEquals(4, $result[2]);

        $result = $this->accessNestedValue($this->testArray, 'a.e[]->z');
        $this->assertEquals(3, $result[0]);
        $this->assertEquals(4, $result[1]);
        $this->assertEquals(5, $result[2]);

        $this->assertEquals(3, $this->accessNestedValue($this->testArray, 'a.e.0->z'));
        $this->assertEquals(4, $this->accessNestedValue($this->testArray, 'a.e.1->z'));
        $this->assertEquals(5, $this->accessNestedValue($this->testArray, 'a.e.2->z'));
    }
}
