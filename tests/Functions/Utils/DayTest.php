<?php

namespace Functions\Utils;

use FQL\Functions\Utils\Day;
use PHPUnit\Framework\TestCase;

class DayTest extends TestCase
{
    public function testDay(): void
    {
        $day = new Day('dateField');
        $result = $day([
            'dateField' => '2023-10-05',
        ], []);

        $this->assertEquals(5, $result);
        $this->assertEquals('DAY(dateField)', (string) $day);
    }

    public function testDayWithInvalidDate(): void
    {
        $day = new Day('dateField');
        $result = $day([
            'dateField' => null,
        ], []);

        $this->assertNull($result);
    }

    public function testDayWithTimestampAndDateTime(): void
    {
        $day = new Day('dateField');
        $timestamp = (new \DateTimeImmutable('2023-10-02'))->getTimestamp();

        $this->assertSame(2, $day(['dateField' => $timestamp], []));
        $this->assertSame(3, $day(['dateField' => new \DateTimeImmutable('2023-10-03')], []));
    }
}
