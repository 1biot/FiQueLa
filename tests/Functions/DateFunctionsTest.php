<?php

namespace Functions;

use FQL\Functions\Utils\DateAdd;
use FQL\Functions\Utils\DateDiff;
use FQL\Functions\Utils\DateFormat;
use FQL\Functions\Utils\DateSub;
use FQL\Functions\Utils\Day;
use FQL\Functions\Utils\FromUnixTime;
use FQL\Functions\Utils\Month;
use FQL\Functions\Utils\Now;
use FQL\Functions\Utils\StrToDate;
use FQL\Functions\Utils\Year;
use PHPUnit\Framework\TestCase;

/**
 * Direct unit coverage of date/time helpers — the values these surface need
 * to be stable regardless of the query pipeline wrapping them.
 */
class DateFunctionsTest extends TestCase
{
    // --- StrToDate ---------------------------------------------------------

    public function testStrToDateBasicIso(): void
    {
        $this->assertSame('2024-01-15', StrToDate::execute('2024-01-15', '%Y-%m-%d'));
    }

    public function testStrToDateWithTime(): void
    {
        // %H:%i:%s → H:i:s
        $result = StrToDate::execute('2024-01-15 14:30:45', '%Y-%m-%d %H:%i:%s');
        $this->assertIsString($result);
        $this->assertStringContainsString('2024-01-15', $result);
    }

    public function testStrToDateReturnsNullOnEmptyValue(): void
    {
        $this->assertNull(StrToDate::execute('', '%Y-%m-%d'));
    }

    public function testStrToDateReturnsNullOnNonString(): void
    {
        $this->assertNull(StrToDate::execute(12345, '%Y-%m-%d'));
    }

    public function testStrToDateReturnsNullOnEmptyFormat(): void
    {
        $this->assertNull(StrToDate::execute('2024-01-15', ''));
    }

    public function testStrToDateReturnsNullForUnparseableInput(): void
    {
        // Value doesn't match the format → null.
        $this->assertNull(StrToDate::execute('not a date', '%Y-%m-%d'));
    }

    public function testStrToDateFormatVariants(): void
    {
        // Year + month + day in different orderings.
        $this->assertSame('2024-01-15', StrToDate::execute('15/01/2024', '%d/%m/%Y'));
        $this->assertSame('2024-01-15', StrToDate::execute('01-15-2024', '%m-%d-%Y'));
        $this->assertSame('2024-01-15', StrToDate::execute('January 15, 2024', '%M %e, %Y'));
    }

    public function testStrToDateShortMonthDayNames(): void
    {
        $result = StrToDate::execute('Jan 15, 2024', '%b %e, %Y');
        $this->assertSame('2024-01-15', $result);
    }

    public function testStrToDateWithAmPm(): void
    {
        $result = StrToDate::execute('02:30:00 PM', '%h:%i:%s %p');
        $this->assertIsString($result);
        $this->assertStringContainsString('14:30:00', $result);
    }

    public function testStrToDateRejectsPartialDateFormat(): void
    {
        // Only %Y without month/day → helper rejects.
        $this->assertNull(StrToDate::execute('2024', '%Y'));
    }

    public function testStrToDateRejectsTimeWithoutAllParts(): void
    {
        // Only %H (no minutes) → helper rejects to avoid ambiguous output.
        $this->assertNull(StrToDate::execute('14', '%H'));
    }

    public function testStrToDateLiteralCharactersInFormat(): void
    {
        // Non-format literals (the " ") should pass through.
        $result = StrToDate::execute('2024 year 01 month 15 day', '%Y year %m month %d day');
        $this->assertSame('2024-01-15', $result);
    }

    public function testStrToDatePercentLiteral(): void
    {
        // `%%` escapes a literal percent.
        $result = StrToDate::execute('50%-2024-01-15', '50%%-%Y-%m-%d');
        $this->assertSame('2024-01-15', $result);
    }

    // --- DateFormat --------------------------------------------------------

    public function testDateFormatFromString(): void
    {
        $result = DateFormat::execute('2024-01-15', 'Y/m/d');
        $this->assertSame('2024/01/15', $result);
    }

    public function testDateFormatReturnsNullForNull(): void
    {
        $this->assertNull(DateFormat::execute(null, 'Y-m-d'));
    }

    public function testDateFormatHandlesTimestamp(): void
    {
        $ts = 1705320000; // 2024-01-15 12:00:00 UTC
        $result = DateFormat::execute(date('c', $ts), 'Y');
        $this->assertSame('2024', $result);
    }

    // --- FromUnixTime ------------------------------------------------------

    public function testFromUnixTimeDefaultFormat(): void
    {
        $this->assertIsString(FromUnixTime::execute(1705320000));
    }

    public function testFromUnixTimeCustomFormat(): void
    {
        $result = FromUnixTime::execute(1705320000, 'Y');
        $this->assertSame('2024', $result);
    }

    public function testFromUnixTimeAcceptsNumericString(): void
    {
        $result = FromUnixTime::execute('1705320000', 'Y');
        $this->assertSame('2024', $result);
    }

    public function testFromUnixTimeReturnsNullOnInvalid(): void
    {
        $this->assertNull(FromUnixTime::execute('not numeric'));
    }

    // --- DateDiff / DateAdd / DateSub -------------------------------------

    public function testDateDiffDays(): void
    {
        // DateDiff returns signed difference in days: second - first.
        $this->assertSame(-5, DateDiff::execute('2024-01-20', '2024-01-15'));
        $this->assertSame(5, DateDiff::execute('2024-01-15', '2024-01-20'));
    }

    public function testDateDiffSameDateIsZero(): void
    {
        $this->assertSame(0, DateDiff::execute('2024-01-15', '2024-01-15'));
    }

    public function testDateDiffNullForInvalid(): void
    {
        $this->assertNull(DateDiff::execute('bogus', '2024-01-01'));
    }

    public function testDateAdd(): void
    {
        $result = DateAdd::execute('2024-01-15', '+1 day');
        $this->assertStringContainsString('2024-01-16', $result);
    }

    public function testDateAddNullForInvalidValue(): void
    {
        $this->assertNull(DateAdd::execute('bogus', '+1 day'));
    }

    public function testDateSub(): void
    {
        $result = DateSub::execute('2024-01-15', '1 day');
        $this->assertStringContainsString('2024-01-14', $result);
    }

    public function testDateSubNullForInvalid(): void
    {
        $this->assertNull(DateSub::execute('bogus', '1 day'));
    }

    // --- Year / Month / Day ------------------------------------------------

    public function testYearExtractsYear(): void
    {
        $this->assertSame(2024, Year::execute('2024-01-15'));
    }

    public function testMonthExtractsMonth(): void
    {
        $this->assertSame(1, Month::execute('2024-01-15'));
    }

    public function testDayExtractsDay(): void
    {
        $this->assertSame(15, Day::execute('2024-01-15'));
    }

    public function testYearMonthDayReturnNullForInvalid(): void
    {
        $this->assertNull(Year::execute('not a date'));
        $this->assertNull(Month::execute('not a date'));
        $this->assertNull(Day::execute('not a date'));
    }

    // --- Now ---------------------------------------------------------------

    public function testNowReturnsFormattedString(): void
    {
        $now = Now::execute();
        $this->assertIsString($now);
        // Looks like a timestamp — has at least one digit.
        $this->assertMatchesRegularExpression('/\d+/', $now);
    }

    public function testNowNumericReturnsLargeInt(): void
    {
        $now = Now::execute(true);
        $this->assertIsInt($now);
        $this->assertGreaterThan(0, $now);
    }
}
