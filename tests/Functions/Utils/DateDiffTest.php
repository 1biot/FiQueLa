<?php

namespace Functions\Utils;

use FQL\Functions\Utils\DateDiff;
use PHPUnit\Framework\TestCase;

class DateDiffTest extends TestCase
{
    public function testDateDiff(): void
    {
        $dateDiff = new DateDiff('fieldDate', 'fieldDate2');
        $result = $dateDiff([
            'fieldDate' => '2023-10-01',
            'fieldDate2' => '2023-10-05',
        ], []);
        $this->assertEquals(4, $result);

        $dateDiffString = (string) $dateDiff;
        $this->assertEquals('DATE_DIFF(fieldDate, fieldDate2)', $dateDiffString);
    }

    public function testDateDiffWithNegativeResult(): void
    {
        $dateDiff = new DateDiff('fieldDate', 'fieldDate2');
        $result = $dateDiff([
            'fieldDate' => '2023-10-05',
            'fieldDate2' => '2023-10-01',
        ], []);
        $this->assertEquals(-4, $result);

        $dateDiffString = (string) $dateDiff;
        $this->assertEquals('DATE_DIFF(fieldDate, fieldDate2)', $dateDiffString);
    }

    public function testDateDiffWithSameDate(): void
    {
        $dateDiff = new DateDiff('fieldDate', 'fieldDate2');
        $result = $dateDiff([
            'fieldDate' => '2023-10-01',
            'fieldDate2' => '2023-10-01',
        ], []);
        $this->assertEquals(0, $result);

        $dateDiffString = (string) $dateDiff;
        $this->assertEquals('DATE_DIFF(fieldDate, fieldDate2)', $dateDiffString);
    }

    public function testDateDiffWithNullValues(): void
    {
        $dateDiff = new DateDiff('fieldDate', 'fieldDate2');
        $result = $dateDiff([
            'fieldDate' => null,
            'fieldDate2' => null,
        ], []);
        $this->assertNull($result);

        $dateDiffString = (string) $dateDiff;
        $this->assertEquals('DATE_DIFF(fieldDate, fieldDate2)', $dateDiffString);
    }
}
