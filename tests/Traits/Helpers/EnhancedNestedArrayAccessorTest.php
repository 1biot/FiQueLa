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

    public function testRemoveNestedValue(): void
    {
        $data = $this->testArray;
        $this->removeNestedValue($data, 'a.d');

        $this->assertArrayNotHasKey('d', $data['a']);
    }

    public function testRemoveNestedValueWithNullValue(): void
    {
        $data = [
            'name' => 'test',
            '_raw' => 'raw line',
            '_error' => null,
        ];

        $this->removeNestedValue($data, '_raw');
        $this->assertArrayNotHasKey('_raw', $data);

        $this->removeNestedValue($data, '_error');
        $this->assertArrayNotHasKey('_error', $data);

        $this->assertArrayHasKey('name', $data);
    }

    public function testRemoveMultipleFieldsIncludingNull(): void
    {
        $data = [
            'id' => 1,
            'value' => 'hello',
            'nullable' => null,
            'zero' => 0,
            'empty' => '',
            'false' => false,
        ];

        $fieldsToRemove = ['nullable', 'zero', 'empty', 'false'];
        foreach ($fieldsToRemove as $field) {
            $this->removeNestedValue($data, $field);
        }

        $this->assertSame(['id', 'value'], array_keys($data));
    }

    public function testInvalidIterationPathThrows(): void
    {
        $this->expectException(\FQL\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path: [] cannot be at the start');

        $this->accessNestedValue($this->testArray, '[]');
    }

    public function testMissingPathReturnsNullWhenNotThrowing(): void
    {
        $this->assertNull($this->accessNestedValue($this->testArray, 'a.missing', false));
    }

    public function testIsAssocDetectsLists(): void
    {
        $this->assertTrue($this->isAssoc(['a' => 1]));
        $this->assertFalse($this->isAssoc([1, 2, 3]));
    }

    public function testScalarIndexingWrapsScalar(): void
    {
        $data = ['a' => 'value'];

        $this->assertSame('value', $this->accessNestedValue($data, 'a.0'));
    }

    public function testAccessNestedValueThrowsWhenScalarKey(): void
    {
        $data = ['a' => 'value'];

        $this->expectException(\FQL\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected array, got string');

        $this->accessNestedValue($data, 'a.b');
    }

    public function testValueIntoArray(): void
    {
        $data = [
            'a' => 'value',
            'b' => null,
        ];
        $this->assertSame(['value'], $this->accessNestedValue($data, 'a[]'));
        $this->assertSame([null], $this->accessNestedValue($data, 'b[]', false));
        $this->assertNull($this->accessNestedValue($data, 'c[]', false));
    }

    public function testWildcardExpansion(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = $this->accessNestedValue($data, '*');
        // Wildcard returns the full map when terminal; values preserved.
        $this->assertSame([1, 2, 3], array_values($result));
    }

    public function testWildcardWithSubPath(): void
    {
        $data = [
            'item1' => ['name' => 'A', 'price' => 10],
            'item2' => ['name' => 'B', 'price' => 20],
        ];
        $result = $this->accessNestedValue($data, '*.name');
        $this->assertSame('A', $result['item1']);
        $this->assertSame('B', $result['item2']);
    }

    public function testWildcardOnScalarThrowsInThrowMode(): void
    {
        $this->expectException(\FQL\Exception\InvalidArgumentException::class);
        $this->accessNestedValue(['x' => 'scalar'], 'x.*', true);
    }

    public function testWildcardOnScalarReturnsNullInNonThrowMode(): void
    {
        $this->assertNull($this->accessNestedValue(['x' => 'scalar'], 'x.*', false));
    }

    public function testDeepMissingKeyThrowsWithFieldName(): void
    {
        $this->expectException(\FQL\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "missing"');
        $this->accessNestedValue(['a' => ['b' => 1]], 'a.missing', true);
    }

    public function testCompiledPathIsReusedAcrossRows(): void
    {
        $rows = [
            ['id' => 1, 'a' => ['b' => 'x'], 'list' => [['v' => 1], ['v' => 2]]],
            ['id' => 2, 'a' => ['b' => 'y'], 'list' => [['v' => 3]]],
            ['id' => null, 'a' => ['b' => null], 'list' => []],
            [],
        ];
        $expected = [
            [1, 'x', [1, 2]],
            [2, 'y', [3]],
            // An empty list is treated as associative and wrapped, see isAssoc().
            [null, null, [null]],
            [null, null, null],
        ];

        foreach ([0, 1] as $pass) {
            foreach ($rows as $index => $row) {
                $this->assertSame($expected[$index][0], $this->accessNestedValue($row, 'id', false), "pass $pass");
                $this->assertSame($expected[$index][1], $this->accessNestedValue($row, 'a.b', false), "pass $pass");
                $this->assertSame($expected[$index][2], $this->accessNestedValue($row, 'list[].v', false), "pass $pass");
            }
        }
    }

    public function testSimpleKeyMatchesTokenizedSemantics(): void
    {
        $data = ['0' => 'zero', 'space key' => 1, 'a*b' => 2, 'nullable' => null];

        $this->assertSame('zero', $this->accessNestedValue($data, '0'));
        $this->assertSame(1, $this->accessNestedValue($data, 'space key'));
        $this->assertSame(2, $this->accessNestedValue($data, 'a*b'));
        $this->assertNull($this->accessNestedValue($data, 'nullable'));
        $this->assertSame(1, $this->accessNestedValue($data, '`space key`'));
        $this->assertNull($this->accessNestedValue($data, '', false));
        $this->assertSame($data, $this->accessNestedValue($data, '*'));
    }

    public function testSimpleMissingKeyThrowsOnEveryAccess(): void
    {
        $this->assertNull($this->accessNestedValue(['id' => 1], 'price', false));

        $this->expectException(\FQL\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "price" not found');
        $this->accessNestedValue(['id' => 1], 'price');
    }

    public function testInvalidPathThrowsOnRepeatedAccess(): void
    {
        foreach ([1, 2] as $attempt) {
            try {
                $this->accessNestedValue($this->testArray, '[].a', false);
                $this->fail("Attempt $attempt should throw");
            } catch (\FQL\Exception\InvalidArgumentException $e) {
                $this->assertSame('Invalid path: [] cannot be at the start', $e->getMessage());
            }
        }
    }
}
