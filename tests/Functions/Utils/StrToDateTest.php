<?php

namespace Functions\Utils;

use FQL\Functions\Utils\StrToDate;
use PHPUnit\Framework\TestCase;

class StrToDateTest extends TestCase
{
    public function testDateParsing(): void
    {
        $strToDate = new StrToDate('dateField', '%d,%m,%Y');
        $this->assertSame('2013-05-01', $strToDate(['dateField' => '01,5,2013'], []));

        $strToDate = new StrToDate('dateField', '%M %e, %Y');
        $this->assertSame('2013-05-01', $strToDate(['dateField' => 'May 1, 2013'], []));
    }

    public function testDateTimeParsing(): void
    {
        $strToDate = new StrToDate('value', '%Y-%m-%d %H:%i:%s');
        $this->assertSame('2023-10-01 09:30:17', $strToDate(['value' => '2023-10-01 09:30:17'], []));
    }

    public function testTimeParsing(): void
    {
        $strToDate = new StrToDate('value', 'a%h:%i:%s');
        $this->assertSame('09:30:17', $strToDate(['value' => 'a09:30:17'], []));

        $strToDate = new StrToDate('value', '%H:%i:%s');
        $this->assertSame('09:30:17', $strToDate(['value' => '09:30:17a'], []));
    }

    public function testInvalidParsing(): void
    {
        $strToDate = new StrToDate('value', '%h:%i:%s');
        $this->assertNull($strToDate(['value' => 'a09:30:17'], []));

        $strToDate = new StrToDate('value', '%Y-%m-%d');
        $this->assertNull($strToDate(['value' => '2023-02-31'], []));
    }

    public function testUnsupportedFormatReturnsNull(): void
    {
        $strToDate = new StrToDate('value', '%Q');
        $this->assertNull($strToDate(['value' => '2023'], []));

        $strToDate = new StrToDate('value', 'abc');
        $this->assertNull($strToDate(['value' => 'abc'], []));
    }

    public function testMissingRequiredPartsReturnsNull(): void
    {
        $strToDate = new StrToDate('value', '%Y-%m');
        $this->assertNull($strToDate(['value' => '2023-10'], []));

        $strToDate = new StrToDate('value', '%H');
        $this->assertNull($strToDate(['value' => '09'], []));

        $strToDate = new StrToDate('value', '%u-%W');
        $this->assertNull($strToDate(['value' => '40-Monday'], []));
    }

    public function testTrailingDataIsTrimmed(): void
    {
        $strToDate = new StrToDate('value', '%Y-%m-%d');
        $this->assertSame('2023-10-01', $strToDate(['value' => '2023-10-01 trailing'], []));
    }

    public function testMicrosecondsOutput(): void
    {
        $strToDate = new StrToDate('value', '%Y-%m-%d %H:%i:%s.%f');
        $this->assertSame(
            '2023-10-01 09:30:17.123456',
            $strToDate(['value' => '2023-10-01 09:30:17.123456'], [])
        );
    }

    public function testWeekBasedDateParsing(): void
    {
        $strToDate = new StrToDate('value', '%X-%V-%w');
        $this->assertNull($strToDate(['value' => '2023-40-1'], []));
    }

    public function testToString(): void
    {
        $strToDate = new StrToDate('value', '%Y-%m-%d');

        $this->assertSame('STR_TO_DATE(value, "%Y-%m-%d")', (string) $strToDate);
    }
}
