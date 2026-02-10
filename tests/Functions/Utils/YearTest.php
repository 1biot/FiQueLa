<?php

namespace Functions\Utils;

use FQL\Functions\Utils\Year;
use PHPUnit\Framework\TestCase;

class YearTest extends TestCase
{
    public function testYear(): void
    {
        $year = new Year('dateField');
        $result = $year([
            'dateField' => '2023-10-01',
        ], []);

        $this->assertEquals(2023, $result);
        $this->assertEquals('YEAR(dateField)', (string) $year);
    }

    public function testYearWithInvalidDate(): void
    {
        $year = new Year('dateField');
        $result = $year([
            'dateField' => 'invalid-date',
        ], []);

        $this->assertNull($result);
    }

    public function testYearWithTimestampAndDateTime(): void
    {
        $year = new Year('dateField');
        $timestamp = (new \DateTimeImmutable('2022-11-02'))->getTimestamp();

        $this->assertSame(2022, $year(['dateField' => $timestamp], []));
        $this->assertSame(2023, $year(['dateField' => new \DateTimeImmutable('2023-12-03')], []));
    }
}
