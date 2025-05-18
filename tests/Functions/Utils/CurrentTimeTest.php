<?php

namespace Functions\Utils;

use FQL\Functions\Utils\CurrentTime;
use PHPUnit\Framework\TestCase;

class CurrentTimeTest extends TestCase
{
    public function testCurrentTime(): void
    {
        $currentTime = new CurrentTime();
        $result = $currentTime();
        $this->assertEquals(date('H:i:s'), $result);

        $currentTimeNumeric = new CurrentTime(true);
        $resultNumeric = $currentTimeNumeric();
        $this->assertEquals(date('His'), $resultNumeric);
        $this->assertIsInt($resultNumeric);

        $currentTimeString = (string) $currentTime;
        $this->assertEquals('CURTIME(false)', $currentTimeString);

        $currentTimeNumericString = (string) $currentTimeNumeric;
        $this->assertEquals('CURTIME(true)', $currentTimeNumericString);
    }
}
