<?php

namespace Functions;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Aggregate\Avg;
use FQL\Functions\Aggregate\Count;
use FQL\Functions\Aggregate\GroupConcat;
use FQL\Functions\Aggregate\Max;
use FQL\Functions\Aggregate\Min;
use FQL\Functions\Aggregate\Sum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage for the static `initial() / accumulate() / finalize()`
 * contract on every built-in aggregate class. The runtime `Stream::applyGrouping`
 * drives aggregates through this same triple, so the tests mirror what happens
 * there but without the streaming plumbing.
 */
final class AggregatesTest extends TestCase
{
    /**
     * @param class-string<\FQL\Functions\Core\AggregateFunction> $class
     * @param list<mixed> $values
     */
    private function driveAggregate(string $class, array $values, array $options = []): mixed
    {
        $acc = $class::initial($options);
        foreach ($values as $value) {
            $acc = $class::accumulate($acc, $value);
        }
        return $class::finalize($acc);
    }

    public function testSum(): void
    {
        $this->assertSame(6, $this->driveAggregate(Sum::class, [1, 2, 3]));
        // Nulls are silently skipped.
        $this->assertEquals(5, $this->driveAggregate(Sum::class, [null, 2, 3, null]));
        // DISTINCT drops repeats before summing.
        $this->assertEquals(6, $this->driveAggregate(Sum::class, [1, 2, 2, 3], ['distinct' => true]));
    }

    public function testSumRejectsNonNumeric(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->driveAggregate(Sum::class, ['not-a-number']);
    }

    public function testAvg(): void
    {
        $this->assertEqualsWithDelta(2.0, $this->driveAggregate(Avg::class, [1, 2, 3]), 1e-9);
        // Empty group returns 0 rather than a division-by-zero.
        $this->assertSame(0, $this->driveAggregate(Avg::class, []));
        $this->assertSame(0, $this->driveAggregate(Avg::class, [null, null]));
        // DISTINCT
        $this->assertEqualsWithDelta(
            2.0,
            $this->driveAggregate(Avg::class, [1, 2, 2, 3], ['distinct' => true]),
            1e-9
        );
    }

    public function testAvgRejectsNonNumeric(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->driveAggregate(Avg::class, ['word']);
    }

    public function testCount(): void
    {
        $this->assertSame(3, $this->driveAggregate(Count::class, [1, 2, 3]));
        $this->assertSame(0, $this->driveAggregate(Count::class, []));
        // Nulls don't count.
        $this->assertSame(2, $this->driveAggregate(Count::class, [1, null, 2]));
        // DISTINCT drops duplicates.
        $this->assertSame(2, $this->driveAggregate(Count::class, ['a', 'a', 'b'], ['distinct' => true]));
    }

    public function testMinMax(): void
    {
        $this->assertEquals(1, $this->driveAggregate(Min::class, [3, 1, 2]));
        $this->assertEquals(3, $this->driveAggregate(Max::class, [3, 1, 2]));
        $this->assertNull($this->driveAggregate(Min::class, []));
        $this->assertNull($this->driveAggregate(Max::class, []));
        // Nulls are ignored.
        $this->assertEquals(5, $this->driveAggregate(Max::class, [null, 5, null, 2]));
    }

    public function testMinMaxNumericStrings(): void
    {
        $this->assertSame(2, $this->driveAggregate(Min::class, ['10', '2', '3']));
        $this->assertSame(10, $this->driveAggregate(Max::class, ['10', '2', '3']));
        $this->assertSame(1.5, $this->driveAggregate(Min::class, ['1,5', 2]));
    }

    /**
     * @return iterable<string, array{list<mixed>, mixed, mixed}>
     */
    public static function dateProvider(): iterable
    {
        yield 'datetime' => [
            ['2026-08-16 10:15:51', '2026-08-16 09:00:00', '2026-08-17 00:00:00'],
            '2026-08-16 09:00:00',
            '2026-08-17 00:00:00',
        ];
        yield 'mixed date and datetime' => [
            ['2026-08-16 10:15:51', '2026-08-16', '2026-08-15 23:59:59'],
            '2026-08-15 23:59:59',
            '2026-08-16 10:15:51',
        ];
        // Chronological order differs from string order here.
        yield 'day-first format' => [
            ['16.8.2026', '2.9.2026', '1.10.2025'],
            '1.10.2025',
            '2.9.2026',
        ];
        // 10:00+02:00 is 08:00 UTC, so it is the earlier instant.
        yield 'time zones' => [
            ['2026-08-16T09:30:00Z', '2026-08-16T10:00:00+02:00'],
            '2026-08-16T10:00:00+02:00',
            '2026-08-16T09:30:00Z',
        ];
        yield 'microseconds' => [
            ['2026-08-16 10:00:00.000002', '2026-08-16 10:00:00.000001'],
            '2026-08-16 10:00:00.000001',
            '2026-08-16 10:00:00.000002',
        ];
        yield 'time only' => [
            ['10:15', '9:59:59', '23:00'],
            '9:59:59',
            '23:00',
        ];
        yield 'RFC 2822' => [
            ['Mon, 17 Aug 2026 08:00:00 GMT', 'Sun, 16 Aug 2026 10:15:51 GMT'],
            'Sun, 16 Aug 2026 10:15:51 GMT',
            'Mon, 17 Aug 2026 08:00:00 GMT',
        ];
        yield 'nulls skipped' => [
            [null, '2026-08-16', null, '2026-08-15'],
            '2026-08-15',
            '2026-08-16',
        ];
    }

    /**
     * @param list<mixed> $values
     */
    #[DataProvider('dateProvider')]
    public function testMinMaxDates(array $values, mixed $expectedMin, mixed $expectedMax): void
    {
        $this->assertSame($expectedMin, $this->driveAggregate(Min::class, $values));
        $this->assertSame($expectedMax, $this->driveAggregate(Max::class, $values));
    }

    /**
     * Empty cells in a CSV / XLSX column arrive as '' and count as NULL.
     *
     * @return iterable<string, array{list<mixed>, mixed, mixed}>
     */
    public static function emptyStringProvider(): iterable
    {
        yield 'numbers' => [['', '10', ' ', '2', ''], 2, 10];
        yield 'dates' => [['2026-08-16', '', '2026-08-15', "\t"], '2026-08-15', '2026-08-16'];
        yield 'only empty' => [['', ' ', ''], null, null];
    }

    /**
     * @param list<mixed> $values
     */
    #[DataProvider('emptyStringProvider')]
    public function testMinMaxSkipEmptyStrings(array $values, mixed $expectedMin, mixed $expectedMax): void
    {
        $this->assertSame($expectedMin, $this->driveAggregate(Min::class, $values));
        $this->assertSame($expectedMax, $this->driveAggregate(Max::class, $values));
    }

    public function testMinMaxDateTimeObjects(): void
    {
        $early = new \DateTimeImmutable('2026-01-01 00:00:00');
        $late = new \DateTime('2026-01-02 00:00:00');
        // Objects compare with date strings and come back unchanged.
        $this->assertSame($early, $this->driveAggregate(Min::class, [$late, $early, '2026-01-01 12:00:00']));
        $this->assertSame($late, $this->driveAggregate(Max::class, [$late, $early, '2026-01-01 12:00:00']));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonComparableProvider(): iterable
    {
        yield 'word' => ['abc'];
        yield 'boolean' => [true];
        yield 'relative keyword' => ['today'];
        yield 'weekday keyword' => ['monday'];
        yield 'relative offset' => ['+1 day'];
        yield 'relative with date' => ['2026-08-16 +1 day'];
        yield 'impossible date' => ['2026-02-30'];
        yield 'array' => [[1, 2]];
    }

    #[DataProvider('nonComparableProvider')]
    public function testMinRejectsNonComparable(mixed $value): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('MIN value is neither numeric nor a date');
        $this->driveAggregate(Min::class, [$value]);
    }

    #[DataProvider('nonComparableProvider')]
    public function testMaxRejectsNonComparable(mixed $value): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('MAX value is neither numeric nor a date');
        $this->driveAggregate(Max::class, [$value]);
    }

    public function testMinRejectsDateMixedWithNumber(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('MIN cannot compare a date with a number');
        $this->driveAggregate(Min::class, ['2026-08-16', 5]);
    }

    public function testMaxRejectsNumberMixedWithDate(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('MAX cannot compare a date with a number');
        $this->driveAggregate(Max::class, [5, '2026-08-16']);
    }

    public function testGroupConcatDefaultSeparator(): void
    {
        $this->assertSame('a,b,c', $this->driveAggregate(GroupConcat::class, ['a', 'b', 'c']));
        $this->assertSame('', $this->driveAggregate(GroupConcat::class, []));
        $this->assertSame('a,b', $this->driveAggregate(GroupConcat::class, ['a', null, 'b']));
    }

    public function testGroupConcatCustomSeparator(): void
    {
        $this->assertSame(
            'a | b | c',
            $this->driveAggregate(GroupConcat::class, ['a', 'b', 'c'], ['separator' => ' | '])
        );
    }

    public function testGroupConcatDistinct(): void
    {
        $this->assertSame(
            'a,b,c',
            $this->driveAggregate(GroupConcat::class, ['a', 'b', 'a', 'c', 'b'], ['distinct' => true])
        );
    }
}
