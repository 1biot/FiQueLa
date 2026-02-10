<?php

namespace Functions\Utils;

use FQL\Functions\Utils\Month;
use PHPUnit\Framework\TestCase;

class MonthTest extends TestCase
{
    public function testMonth(): void
    {
        $month = new Month('dateField');
        $result = $month([
            'dateField' => '2023-10-01',
        ], []);

        $this->assertEquals(10, $result);
        $this->assertEquals('MONTH(dateField)', (string) $month);
    }

    public function testMonthWithInvalidDate(): void
    {
        $month = new Month('dateField');
        $result = $month([
            'dateField' => 'invalid-date',
        ], []);

        $this->assertNull($result);
    }

    public function testMonthWithTimestampAndDateTime(): void
    {
        $month = new Month('dateField');
        $timestamp = (new \DateTimeImmutable('2023-11-02'))->getTimestamp();

        $this->assertSame(11, $month(['dateField' => $timestamp], []));
        $this->assertSame(12, $month(['dateField' => new \DateTimeImmutable('2023-12-03')], []));
    }
}
