<?php

namespace Functions\Utils;

use FQL\Functions\Utils\DateAdd;
use PHPUnit\Framework\TestCase;

class DateAddTest extends TestCase
{
    public function testDateAdd(): void
    {
        $dateAdd = new DateAdd('dateField', '+2 hours');
        $result = $dateAdd([
            'dateField' => '2023-10-01 12:00:00',
        ], []);

        $this->assertEquals('2023-10-01 14:00:00', $result);
        $this->assertEquals('DATE_ADD(dateField, "+2 hours")', (string) $dateAdd);
    }

    public function testDateAddWithInvalidDate(): void
    {
        $dateAdd = new DateAdd('dateField', '+1 day');
        $result = $dateAdd([
            'dateField' => 'invalid-date',
        ], []);

        $this->assertNull($result);
    }
}
