<?php

namespace Functions;

use FQL\Functions\Hashing\Md5;
use FQL\Functions\Hashing\Sha1;
use FQL\Functions\Math\Ceil;
use FQL\Functions\Math\Divide;
use FQL\Functions\Math\Floor;
use FQL\Functions\Math\Mod;
use FQL\Functions\Math\Multiply;
use FQL\Functions\Math\Round;
use FQL\Functions\Math\Sub;
use FQL\Functions\String\Base64Decode;
use FQL\Functions\String\Base64Encode;
use FQL\Functions\String\Explode;
use FQL\Functions\String\Implode;
use FQL\Functions\String\LeftPad;
use FQL\Functions\String\Locate;
use FQL\Functions\String\Lower;
use FQL\Functions\String\Replace;
use FQL\Functions\String\Reverse;
use FQL\Functions\String\RightPad;
use FQL\Functions\String\Substring;
use FQL\Functions\String\Upper;
use FQL\Functions\Utils\ArrayCombine;
use FQL\Functions\Utils\ArrayFilter;
use FQL\Functions\Utils\ArrayMerge;
use FQL\Functions\Utils\ArraySearch;
use FQL\Functions\Utils\ColSplit;
use FQL\Functions\Utils\Length;
use FQL\Functions\Utils\RandomBytes;
use FQL\Functions\Utils\SelectIfNull;
use FQL\Functions\Utils\SelectIsNull;
use FQL\Functions\Utils\Uuid;
use PHPUnit\Framework\TestCase;

/**
 * Quick unit-level sweep of the small scalar function implementations.
 * Each helper is tested through its public static `execute()` entry with a
 * handful of inputs covering the happy path, a null / empty input, and an
 * invalid / unexpected argument type where it makes sense.
 */
class SmallFunctionsTest extends TestCase
{
    // Math
    public function testCeil(): void
    {
        $this->assertSame(3.0, Ceil::execute(2.1));
        $this->assertSame(2.0, Ceil::execute(2));
    }

    public function testFloor(): void
    {
        $this->assertSame(2.0, Floor::execute(2.9));
        $this->assertSame(2.0, Floor::execute(2));
    }

    public function testRound(): void
    {
        $this->assertSame(3.0, Round::execute(2.5));
        $this->assertSame(2.1, Round::execute(2.14, 1));
    }

    public function testMod(): void
    {
        // Mod uses fmod internally → always float result.
        $this->assertEqualsWithDelta(1.0, Mod::execute(10, 3), 0.0001);
        $this->assertEqualsWithDelta(0.5, Mod::execute(2.5, 1), 0.0001);
    }

    public function testMultiply(): void
    {
        $this->assertSame(24, Multiply::execute(2, 3, 4));
        $this->assertSame(0, Multiply::execute(0, 5));
    }

    public function testDivide(): void
    {
        $this->assertEqualsWithDelta(5.0, Divide::execute(10, 2), 0.0001);
    }

    public function testDivideByZeroThrows(): void
    {
        $this->expectException(\FQL\Exception\UnexpectedValueException::class);
        Divide::execute(10, 0);
    }

    public function testSub(): void
    {
        $this->assertSame(3, Sub::execute(10, 5, 2));
    }

    // String
    public function testLower(): void
    {
        $this->assertSame('abc', Lower::execute('ABC'));
    }

    public function testUpper(): void
    {
        $this->assertSame('ABC', Upper::execute('abc'));
    }

    public function testReverse(): void
    {
        $this->assertSame('cba', Reverse::execute('abc'));
    }

    public function testBase64RoundTrip(): void
    {
        $encoded = Base64Encode::execute('hello');
        $this->assertSame('hello', Base64Decode::execute($encoded));
    }

    public function testLeftPadRightPad(): void
    {
        $this->assertSame('00042', LeftPad::execute('42', 5, '0'));
        $this->assertSame('42000', RightPad::execute('42', 5, '0'));
    }

    public function testPadAcceptsNull(): void
    {
        // Pad functions coerce null to '' then pad.
        $this->assertSame('     ', LeftPad::execute(null, 5));
        $this->assertSame('     ', RightPad::execute(null, 5));
    }

    public function testReplace(): void
    {
        $this->assertSame('Hallo World', Replace::execute('Hello World', 'e', 'a'));
    }

    public function testSubstring(): void
    {
        $this->assertSame('ell', Substring::execute('Hello', 1, 3));
        $this->assertSame('ello', Substring::execute('Hello', 1));
    }

    public function testLocate(): void
    {
        $this->assertSame(3, Locate::execute('l', 'Hello'));   // 1-based: "Hel" → l at position 3
        $this->assertSame(0, Locate::execute('x', 'Hello'));   // not found → 0 (SQL convention)
    }

    public function testExplodeImplode(): void
    {
        $this->assertSame(['a', 'b', 'c'], Explode::execute('a,b,c', ','));
        $this->assertSame('a-b-c', Implode::execute(['a', 'b', 'c'], '-'));
    }

    // Utils
    public function testLength(): void
    {
        $this->assertSame(5, Length::execute('hello'));
        $this->assertSame(0, Length::execute(''));
    }

    public function testArrayCombine(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2],
            ArrayCombine::execute(['a', 'b'], [1, 2])
        );
    }

    public function testArrayCombineMismatch(): void
    {
        // Array length mismatch — helper currently lets PHP throw; ensure it
        // raises rather than silently producing garbage.
        $this->expectException(\ValueError::class);
        ArrayCombine::execute(['a'], [1, 2]);
    }

    public function testArrayMerge(): void
    {
        $this->assertSame([1, 2, 3, 4], ArrayMerge::execute([1, 2], [3, 4]));
    }

    public function testArrayFilter(): void
    {
        // array_filter preserves keys; arrayFilter strips falsy values but
        // reindexes. Only assert the resulting values.
        $this->assertSame([1, 3], array_values(ArrayFilter::execute([1, 0, 3, null, ''])));
    }

    public function testArraySearch(): void
    {
        $this->assertSame(1, ArraySearch::execute(['a', 'b', 'c'], 'b'));
        $this->assertFalse(ArraySearch::execute(['a', 'b'], 'x'));
    }

    public function testColSplitReturnsNullForNonArrayInput(): void
    {
        $this->assertNull(ColSplit::execute('not-an-array'));
    }

    public function testColSplitSpreadsArrayIntoColumns(): void
    {
        $result = ColSplit::execute(['a', 'b', 'c'], 'item_%index');
        $this->assertSame([
            'item_1' => 'a',
            'item_2' => 'b',
            'item_3' => 'c',
        ], $result);
    }

    public function testColSplitUsesKeyField(): void
    {
        $result = ColSplit::execute(
            [['id' => 'x', 'v' => 1], ['id' => 'y', 'v' => 2]],
            'field_%index',
            'id'
        );
        $this->assertArrayHasKey('field_x', $result);
        $this->assertArrayHasKey('field_y', $result);
    }

    public function testSelectIsNull(): void
    {
        $this->assertTrue(SelectIsNull::execute(null));
        $this->assertFalse(SelectIsNull::execute('x'));
        $this->assertFalse(SelectIsNull::execute(0));
    }

    public function testSelectIfNull(): void
    {
        $this->assertSame('fallback', SelectIfNull::execute(null, 'fallback'));
        $this->assertSame('value', SelectIfNull::execute('value', 'fallback'));
    }

    public function testRandomBytes(): void
    {
        // Returns raw binary bytes of requested length (not hex).
        $result = RandomBytes::execute(10);
        $this->assertIsString($result);
        $this->assertSame(10, strlen($result));
    }

    public function testUuid(): void
    {
        $uuid = Uuid::execute();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    // Hashing
    public function testMd5(): void
    {
        $this->assertSame(md5('hello'), Md5::execute('hello'));
    }

    public function testSha1(): void
    {
        $this->assertSame(sha1('hello'), Sha1::execute('hello'));
    }
}
