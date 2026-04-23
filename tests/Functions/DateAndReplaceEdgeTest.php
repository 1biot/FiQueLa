<?php

namespace Functions;

use FQL\Functions\String\Replace;
use FQL\Functions\Utils\DateAdd;
use FQL\Functions\Utils\DateSub;
use PHPUnit\Framework\TestCase;

/**
 * Covers the interval parsing / strtotime fallback branches in DateAdd /
 * DateSub and the null / invalid-input handling in Replace.
 */
class DateAndReplaceEdgeTest extends TestCase
{
    public function testDateAddWithComplexInterval(): void
    {
        $result = DateAdd::execute('2024-01-15', '+2 weeks');
        $this->assertStringContainsString('2024-01-29', $result);
    }

    public function testDateAddMonthInterval(): void
    {
        $result = DateAdd::execute('2024-01-15', '+1 month');
        $this->assertStringContainsString('2024-02-15', $result);
    }

    public function testDateAddYearInterval(): void
    {
        $result = DateAdd::execute('2024-01-15', '+1 year');
        $this->assertStringContainsString('2025-01-15', $result);
    }

    public function testDateAddReturnsNullForInvalidInterval(): void
    {
        $this->assertNull(DateAdd::execute('2024-01-15', 'not a valid interval'));
    }

    public function testDateAddRejectsNonStringValue(): void
    {
        // DateAdd expects a parseable date string — numeric timestamp yields null.
        $this->assertNull(DateAdd::execute(1705320000, '+1 day'));
    }

    public function testDateSubComplexInterval(): void
    {
        $result = DateSub::execute('2024-03-15', '2 weeks');
        $this->assertStringContainsString('2024-03-01', $result);
    }

    public function testDateSubMonthInterval(): void
    {
        $result = DateSub::execute('2024-03-15', '1 month');
        $this->assertStringContainsString('2024-02-15', $result);
    }

    public function testReplaceNullValue(): void
    {
        $this->assertNull(Replace::execute(null, 'old', 'new'));
    }

    public function testReplaceEmptySearch(): void
    {
        // str_replace on empty search returns subject unchanged.
        $this->assertSame('hello', Replace::execute('hello', '', 'x'));
    }

    public function testReplaceNumericValueStringifies(): void
    {
        // Non-string value is cast to string.
        $this->assertSame('X23', Replace::execute(123, '1', 'X'));
    }

    public function testReplaceArraySearchAndReplace(): void
    {
        // Scalar search and replace — core case.
        $this->assertSame(
            'hallo world',
            Replace::execute('hello world', 'e', 'a')
        );
    }
}
