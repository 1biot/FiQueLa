<?php

namespace Functions\Utils;

use FQL\Functions\Utils\DateFormat;
use PHPUnit\Framework\TestCase;

class DateFormatTest extends TestCase
{
    public function testDateFormat(): void
    {
        $date = new \DateTimeImmutable('2023-10-01 12:00:00');
        $dateFormat = new DateFormat('dateField', 'Y-m-d H:i:s');

        $this->assertEquals(
            '2023-10-01 12:00:00',
            $dateFormat(
                [
                    'dateField' => $date,
                ],
                []
            )
        );
    }

    public function testInvalidDate(): void
    {
        $dateFormat = new DateFormat('dateField', 'Y-m-d H:i:s');
        $this->assertEquals(
            null,
            $dateFormat(
                [
                    'dateField' => 'invalid date',
                ],
                []
            )
        );
    }

    public function testInvalidField(): void
    {
        $dateFormat = new DateFormat('anotherField', 'Y-m-d H:i:s');
        $this->assertEquals(
            null,
            $dateFormat(
                [
                    'anotherField' => new \DateTime('2023-10-01 12:00:00'),
                ],
                []
            )
        );
    }
}
