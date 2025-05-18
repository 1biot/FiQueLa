<?php

namespace Functions\Utils;

use FQL\Functions\Utils\CurrentDay;
use PHPUnit\Framework\TestCase;

class CurrentDayTest extends TestCase
{
    public function testCurrentDay(): void
    {
        $currentDay = new CurrentDay();
        $result = $currentDay();
        $this->assertEquals(date('Y-m-d'), $result);

        $currentDayNumeric = new CurrentDay(true);
        $resultNumeric = $currentDayNumeric();
        $this->assertEquals(date('Ymd'), $resultNumeric);
        $this->assertIsInt($resultNumeric);

        $currentDateString = (string) $currentDay;
        $this->assertEquals('CURDATE(false)', $currentDateString);

        $currentDateNumericString = (string) $currentDayNumeric;
        $this->assertEquals('CURDATE(true)', $currentDateNumericString);
    }
}
