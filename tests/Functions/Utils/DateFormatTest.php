<?php

namespace Functions\Utils;

use FQL\Functions\Utils\DateFormat;
use PHPUnit\Framework\TestCase;

class DateFormatTest extends TestCase
{
    public function testDateFormat(): void
    {
        $date = '2023-10-01 12:00:00';

        $dateFormats = [
            'Y-m-d H:i:s' => '2023-10-01 12:00:00',
            'Y-m-d' => '2023-10-01',
            'Y-m-d H:i' => '2023-10-01 12:00',
            'Y-m-d H' => '2023-10-01 12',
            'Y-m-d H:i:s.u' => '2023-10-01 12:00:00.000000',
        ];

        foreach ($dateFormats as $format => $expected) {
            $dateFormat = new DateFormat('dateField', $format);
            $this->assertEquals(
                $expected,
                $dateFormat(
                    [
                        'dateField' => $date,
                    ],
                    []
                )
            );

            $stringKey = sprintf('"%s"', $date);
            $dateFormat = new DateFormat($stringKey, $format);
            $this->assertEquals(
                $expected,
                $dateFormat(
                    [
                        $date => $date,
                    ],
                    []
                )
            );

            $stringKey = sprintf("'%s'", $date);
            $this->assertEquals(
                $expected,
                $dateFormat(
                    [
                        $date => $date,
                    ],
                    []
                )
            );
        }
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
                    'anotherField' => "hello world",
                ],
                []
            )
        );
    }
}
