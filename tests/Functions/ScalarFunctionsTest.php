<?php

namespace Functions;

use FQL\Enum\Fulltext;
use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Sanity checks for each built-in scalar function's static `execute()` entry
 * point. Keeps the coverage honest after the refactor that replaced instance-
 * based `__invoke($item, $resultItem)` calls with pure static helpers.
 *
 * Runtime-level semantics (field lookup, null propagation, expression
 * evaluation) are covered by `tests/SQL/Runtime/ExpressionEvaluatorTest` and
 * the E2E query suites. Here we only verify that each helper returns what the
 * documented signature promises given direct scalar inputs.
 */
final class ScalarFunctionsTest extends TestCase
{
    public function testHashing(): void
    {
        $this->assertSame(md5('value'), Functions\Hashing\Md5::execute('value'));
        $this->assertSame(md5(''), Functions\Hashing\Md5::execute(null));
        $this->assertSame(sha1('value'), Functions\Hashing\Sha1::execute('value'));
    }

    public function testMathAdd(): void
    {
        $this->assertSame(6, Functions\Math\Add::execute(1, 2, 3));
        $this->assertEqualsWithDelta(3.5, Functions\Math\Add::execute('1.5', 2), 1e-9);
        $this->assertSame(5, Functions\Math\Add::execute(null, 5));
    }

    public function testMathSubMultiplyDivide(): void
    {
        $this->assertSame(5, Functions\Math\Sub::execute(10, 3, 2));
        $this->assertSame(24, Functions\Math\Multiply::execute(2, 3, 4));
        $this->assertEqualsWithDelta(2.5, Functions\Math\Divide::execute(10, 4), 1e-9);
    }

    public function testMathRoundCeilFloor(): void
    {
        $this->assertSame(3.0, Functions\Math\Round::execute(2.7));
        $this->assertSame(2.75, Functions\Math\Round::execute(2.748, 2));
        $this->assertSame(3.0, Functions\Math\Ceil::execute(2.1));
        $this->assertSame(2.0, Functions\Math\Floor::execute(2.9));
    }

    public function testMathRoundRejectsNonNumeric(): void
    {
        $this->expectException(UnexpectedValueException::class);
        Functions\Math\Round::execute('abc');
    }

    public function testMathMod(): void
    {
        $this->assertEquals(1, Functions\Math\Mod::execute(10, 3));
    }

    public function testStringLowerUpperReverseLength(): void
    {
        $this->assertSame('hello', Functions\String\Lower::execute('Hello'));
        $this->assertSame('HELLO', Functions\String\Upper::execute('Hello'));
        $this->assertSame('olleH', Functions\String\Reverse::execute('Hello'));
        $this->assertSame(5, Functions\Utils\Length::execute('Hello'));
    }

    public function testStringConcatAndConcatWs(): void
    {
        $this->assertSame('abc', Functions\String\Concat::execute('a', 'b', 'c'));
        $this->assertSame('a, b, c', Functions\String\ConcatWS::execute(', ', 'a', 'b', 'c'));
    }

    public function testStringBase64(): void
    {
        $encoded = Functions\String\Base64Encode::execute('hello');
        $this->assertSame(base64_encode('hello'), $encoded);
        $this->assertSame('hello', Functions\String\Base64Decode::execute($encoded));
    }

    public function testStringPadReplaceLocate(): void
    {
        $this->assertSame('001', Functions\String\LeftPad::execute('1', 3, '0'));
        $this->assertSame('1..', Functions\String\RightPad::execute('1', 3, '.'));
        $this->assertSame('hola world', Functions\String\Replace::execute('hello world', 'hello', 'hola'));
        $this->assertSame(7, Functions\String\Locate::execute('world', 'hello world'));
    }

    public function testStringSubstringExplodeImplode(): void
    {
        $this->assertSame('ell', Functions\String\Substring::execute('hello', 1, 3));
        $this->assertSame(['a', 'b', 'c'], Functions\String\Explode::execute('a,b,c', ','));
        $this->assertSame('a,b,c', Functions\String\Implode::execute(['a', 'b', 'c'], ','));
    }

    public function testStringRandom(): void
    {
        $s = Functions\String\RandomString::execute(8);
        $this->assertSame(8, strlen($s));
    }

    public function testStringFulltext(): void
    {
        $score = Functions\String\Fulltext::execute(
            ['quick brown fox'],
            'quick fox',
            Fulltext::NATURAL
        );
        $this->assertGreaterThan(0.0, $score);
    }

    public function testUtilsCoalesce(): void
    {
        $this->assertSame('hit', Functions\Utils\Coalesce::execute(null, null, 'hit', 'miss'));
        $this->assertSame('non-empty', Functions\Utils\CoalesceNotEmpty::execute('', null, 'non-empty'));
    }

    public function testUtilsCast(): void
    {
        $this->assertSame(42, Functions\Utils\Cast::execute('42', Type::INTEGER));
    }

    public function testUtilsUuidRandomBytes(): void
    {
        $uuid = Functions\Utils\Uuid::execute();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid
        );
        $this->assertSame(10, strlen(Functions\Utils\RandomBytes::execute(10)));
    }

    public function testUtilsConditionals(): void
    {
        $this->assertTrue(Functions\Utils\SelectIsNull::execute(null));
        $this->assertFalse(Functions\Utils\SelectIsNull::execute(''));
        $this->assertSame('fallback', Functions\Utils\SelectIfNull::execute(null, 'fallback'));
        $this->assertSame('keep', Functions\Utils\SelectIfNull::execute('keep', 'fallback'));
        $this->assertSame('yes', Functions\Utils\SelectIf::execute(true, 'yes', 'no'));
        $this->assertSame('no', Functions\Utils\SelectIf::execute(false, 'yes', 'no'));
    }

    public function testUtilsArrays(): void
    {
        $this->assertSame(['a' => 1, 'b' => 2], Functions\Utils\ArrayCombine::execute(['a', 'b'], [1, 2]));
        $this->assertSame([1, 2, 3, 4], Functions\Utils\ArrayMerge::execute([1, 2], [3, 4]));
        $this->assertSame([1, 3], array_values(Functions\Utils\ArrayFilter::execute([1, 0, 3, null, ''])));
    }

    public function testUtilsDateParts(): void
    {
        $this->assertSame(2024, Functions\Utils\Year::execute('2024-06-15'));
        $this->assertSame(6, Functions\Utils\Month::execute('2024-06-15'));
        $this->assertSame(15, Functions\Utils\Day::execute('2024-06-15'));
    }

    /* ---------------------------------------------------------------- */
    /*  Edge-case coverage for null / empty inputs and error paths.      */
    /* ---------------------------------------------------------------- */

    public function testMathHandlesNullAndEmpty(): void
    {
        // Round treats null as zero.
        $this->assertSame(0.0, Functions\Math\Round::execute(null));
        // Empty string treated as zero by Add.
        $this->assertSame(3, Functions\Math\Add::execute(3, ''));
    }

    public function testMathDivideByZeroThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);
        Functions\Math\Divide::execute(10, 0);
    }

    public function testMathRejectsBadStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);
        Functions\Math\Add::execute('apple', 1);
    }

    public function testMathMultiplyHandlesNull(): void
    {
        // Multiply treats null as a zero factor → product is 0.
        $this->assertSame(0, Functions\Math\Multiply::execute(3, null, 4));
    }

    public function testStringHelpersNullHandling(): void
    {
        $this->assertSame(md5(''), Functions\Hashing\Md5::execute(null));
        $this->assertSame(sha1(''), Functions\Hashing\Sha1::execute(null));
        $this->assertSame('', Functions\String\Lower::execute(null));
        $this->assertSame('', Functions\String\Upper::execute(null));
        // Implode handles scalar silently as single-element list.
        $this->assertSame('', Functions\String\Implode::execute([], ','));
    }

    public function testStringLocateMissingNeedleReturnsZero(): void
    {
        // SQL LOCATE returns 0 when the needle is not found.
        $this->assertSame(0, Functions\String\Locate::execute('zzz', 'abc'));
    }

    public function testStringReplaceNoMatchPreservesValue(): void
    {
        $this->assertSame('abc', Functions\String\Replace::execute('abc', 'X', 'Y'));
    }

    public function testUtilsCoalesceReturnsFirstNonNull(): void
    {
        // COALESCE keeps the first non-null argument (empty string is not null).
        $this->assertSame('', Functions\Utils\Coalesce::execute(null, '', 'keep'));
        // All-null input falls back to an empty string sentinel.
        $this->assertSame('', Functions\Utils\Coalesce::execute(null, null));
        // CoalesceNotEmpty uses empty() semantics — truthy first-hit wins.
        $this->assertSame('keep', Functions\Utils\CoalesceNotEmpty::execute('', null, 'keep'));
    }

    public function testUtilsYearMonthDayHandleBadInput(): void
    {
        $this->assertNull(Functions\Utils\Year::execute('not-a-date'));
        $this->assertNull(Functions\Utils\Month::execute('not-a-date'));
        $this->assertNull(Functions\Utils\Day::execute('not-a-date'));
    }

    public function testDateAddDateSub(): void
    {
        // DATE_ADD/DATE_SUB emit ISO 8601 ("c") by default.
        $this->assertStringStartsWith(
            '2024-01-02',
            (string) Functions\Utils\DateAdd::execute('2024-01-01', '+1 day')
        );
        $this->assertStringStartsWith(
            '2023-12-31',
            (string) Functions\Utils\DateSub::execute('2024-01-01', '+1 day')
        );
    }

    public function testDateDiffInDays(): void
    {
        // DATE_DIFF returns `date1 - date2` in days — can be negative.
        $this->assertSame(-10, Functions\Utils\DateDiff::execute('2024-01-11', '2024-01-01'));
        $this->assertSame(10, Functions\Utils\DateDiff::execute('2024-01-01', '2024-01-11'));
    }

    public function testDateFormatFromUnixTimeStrToDate(): void
    {
        // The helpers accept PHP date() format. Dates with no time default to midnight.
        $this->assertStringStartsWith('2024-01-02', (string) Functions\Utils\DateFormat::execute('2024-01-02'));
        $this->assertStringStartsWith('1970-01-01', (string) Functions\Utils\FromUnixTime::execute(0));
        $this->assertSame(
            '2024-01-02',
            Functions\Utils\StrToDate::execute('02/01/2024', '%d/%m/%Y')
        );
    }

    public function testUtilsCurrentHelpers(): void
    {
        // Numeric variant returns an int, string variant returns string.
        $this->assertIsInt(Functions\Utils\Now::execute(true));
        $this->assertIsString(Functions\Utils\Now::execute(false));
        $this->assertIsInt(Functions\Utils\CurrentDate::execute(true));
        $this->assertIsInt(Functions\Utils\CurrentTime::execute(true));
        $this->assertIsInt(Functions\Utils\CurrentTimestamp::execute());
    }
}
