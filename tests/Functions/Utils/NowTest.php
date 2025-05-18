<?php

namespace Functions\Utils;

use FQL\Functions\Utils\Now;
use PHPUnit\Framework\TestCase;

class NowTest extends TestCase
{
    public function testNow(): void
    {
        $now = new Now();
        $result = $now();
        $this->assertEquals(date('Y-m-d H:i:s'), $result);

        $nowNumeric = new Now(true);
        $resultNumeric = $nowNumeric();
        $this->assertEquals(date('YmdHis'), $resultNumeric);
        $this->assertIsInt($resultNumeric);

        $nowString = (string) $now;
        $this->assertEquals('NOW(false)', $nowString);

        $nowNumericString = (string) $nowNumeric;
        $this->assertEquals('NOW(true)', $nowNumericString);
    }
}
