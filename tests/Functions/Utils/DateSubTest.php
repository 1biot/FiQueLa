<?php

namespace Functions\Utils;

use FQL\Functions\Utils\DateSub;
use PHPUnit\Framework\TestCase;

class DateSubTest extends TestCase
{
    public function testDateSub(): void
    {
        $dateSub = new DateSub('dateField', '+2 hours');
        $result = $dateSub([
            'dateField' => '2023-10-01 12:00:00',
        ], []);

        $this->assertEquals('2023-10-01 10:00:00', $result);
        $this->assertEquals('DATE_SUB(dateField, "+2 hours")', (string) $dateSub);
    }

    public function testDateSubWithInvalidDate(): void
    {
        $dateSub = new DateSub('dateField', '+1 day');
        $result = $dateSub([
            'dateField' => null,
        ], []);

        $this->assertNull($result);
    }
}
