<?php

namespace Functions\Utils;

use FQL\Functions\Utils\DateFormat;
use FQL\Functions\Utils\FromUnixTime;
use PHPUnit\Framework\TestCase;

class FromUnixTimeTest extends TestCase
{
    public function testDateFormat(): void
    {
        $date = '2023-10-01 12:00:00';
        $unixTime = strtotime($date);

        $dateFormats = [
            'Y-m-d H:i:s' => '2023-10-01 12:00:00',
            'Y-m-d' => '2023-10-01',
            'Y-m-d H:i' => '2023-10-01 12:00',
            'Y-m-d H' => '2023-10-01 12',
            'Y-m-d H:i:s.u' => '2023-10-01 12:00:00.000000',
        ];

        foreach ($dateFormats as $format => $expected) {
            $dateFormat = new FromUnixTime('dateField', $format);
            $this->assertEquals(
                $expected,
                $dateFormat(
                    [
                        'dateField' => $unixTime,
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
        $dateFormat = new FromUnixTime('dateField', 'Y-m-d H:i:s');
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
        $dateFormat = new FromUnixTime('anotherField', 'Y-m-d H:i:s');
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
