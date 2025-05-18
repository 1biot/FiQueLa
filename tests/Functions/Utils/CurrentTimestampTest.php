<?php

namespace Functions\Utils;

use FQL\Functions\Utils\CurrentTimestamp;
use PHPUnit\Framework\TestCase;

class CurrentTimestampTest extends TestCase
{
    public function testCurrentTimestamp(): void
    {
        $currentTimestamp = new CurrentTimestamp();
        $result = $currentTimestamp();
        $this->assertEquals(time(), $result);
        $this->assertIsInt($result);

        $currentTimestampString = (string)$currentTimestamp;
        $this->assertEquals('CURRENT_TIMESTAMP()', $currentTimestampString);
    }
}
