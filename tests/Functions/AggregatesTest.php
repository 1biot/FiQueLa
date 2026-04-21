<?php

namespace Functions;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Aggregate\Avg;
use FQL\Functions\Aggregate\Count;
use FQL\Functions\Aggregate\GroupConcat;
use FQL\Functions\Aggregate\Max;
use FQL\Functions\Aggregate\Min;
use FQL\Functions\Aggregate\Sum;
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

    public function testMinRejectsNonNumeric(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->driveAggregate(Min::class, ['abc']);
    }

    public function testMaxRejectsNonNumeric(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->driveAggregate(Max::class, ['abc']);
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
