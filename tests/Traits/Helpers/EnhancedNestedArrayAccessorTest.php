<?php

namespace Traits\Helpers;

use FQL\Traits\Helpers\EnhancedNestedArrayAccessor;
use PHPUnit\Framework\TestCase;

class EnhancedNestedArrayAccessorTest extends TestCase
{
    use EnhancedNestedArrayAccessor;

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
        ],
        'space key' => [
            'key.with.dot' => [
                ['space key 2' => 'value', 'key.with.dots.2' => 'value2'],
                ['space key 2' => 'value3', 'key.with.dots.2' => 'value4']
            ]
        ]
    ];

    public function testGetNestedValue(): void
    {
        $this->assertEquals('value', $this->accessNestedValue($this->testArray, 'a.b.c'));
        $this->assertEquals('value2', $this->accessNestedValue($this->testArray, 'a.d'));

        $result = $this->accessNestedValue($this->testArray, 'a.e[].x');
        $this->assertEquals(1, $result[0]);
        $this->assertEquals(2, $result[1]);
        $this->assertEquals(3, $result[2]);

        $result = $this->accessNestedValue($this->testArray, 'a.e[].y');
        $this->assertEquals(2, $result[0]);
        $this->assertEquals(3, $result[1]);
        $this->assertEquals(4, $result[2]);

        $result = $this->accessNestedValue($this->testArray, 'a.e[].z');
        $this->assertEquals(3, $result[0]);
        $this->assertEquals(4, $result[1]);
        $this->assertEquals(5, $result[2]);

        $this->assertEquals(3, $this->accessNestedValue($this->testArray, 'a.e.0.z'));
        $this->assertEquals(4, $this->accessNestedValue($this->testArray, 'a.e.1.z'));
        $this->assertEquals(5, $this->accessNestedValue($this->testArray, 'a.e.2.z'));

        $result = $this->accessNestedValue($this->testArray, 'a.b[].c');
        $this->assertIsList($result);
        $this->assertEquals('value', $result[0]);

        $result = $this->accessNestedValue($this->testArray, 'a.d[]');
        $this->assertIsList($result);
        $this->assertEquals('value2', $result[0]);
        $this->assertEquals('value2', $this->accessNestedValue($this->testArray, 'a.d[0]'));
        $this->assertEquals($result[0], $this->accessNestedValue($this->testArray, 'a.d[0]'));

        $this->assertEquals(
            'value',
            $this->accessNestedValue($this->testArray, 'space key.`key.with.dot`[0].space key 2')
        );
        $this->assertEquals(
            'value3',
            $this->accessNestedValue($this->testArray, 'space key.`key.with.dot`[1].space key 2')
        );
    }
}
