<?php

namespace Functions\Utils;

use FQL\Functions\Utils\SelectIsNull;
use PHPUnit\Framework\TestCase;

class SelectIsNullTest extends TestCase
{
    public function testSelectIsNull(): void
    {
        $selectIsNull = new SelectIsNull('field');
        $result = $selectIsNull(['field' => '2025'], []);
        $this->assertFalse($result);

        $selectIsNotNull = new SelectIsNull('field2');
        $resultNotNull = $selectIsNotNull(['field' => 'hello world'], []);
        $this->assertTrue($resultNotNull);

        $selectIsNullString = (string) $selectIsNull;
        $this->assertEquals('ISNULL(field)', $selectIsNullString);

        $selectIsNotNullString = (string) $selectIsNotNull;
        $this->assertEquals('ISNULL(field2)', $selectIsNotNullString);
    }
}
