<?php

namespace Functions\Utils;

use FQL\Functions\Utils\CurrentDate;
use PHPUnit\Framework\TestCase;

class CurrentDayTest extends TestCase
{
    public function testCurrentDay(): void
    {
        $currentDay = new CurrentDate();
        $result = $currentDay();
        $this->assertEquals(date('Y-m-d'), $result);

        $currentDayNumeric = new CurrentDate(true);
        $resultNumeric = $currentDayNumeric();
        $this->assertEquals(date('Ymd'), $resultNumeric);
        $this->assertIsInt($resultNumeric);

        $currentDateString = (string) $currentDay;
        $this->assertEquals('CURDATE(false)', $currentDateString);

        $currentDateNumericString = (string) $currentDayNumeric;
        $this->assertEquals('CURDATE(true)', $currentDateNumericString);
    }
}
