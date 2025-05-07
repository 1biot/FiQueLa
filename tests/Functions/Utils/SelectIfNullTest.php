<?php

namespace Functions\Utils;

use FQL\Functions\Utils\SelectIfNull;
use PHPUnit\Framework\TestCase;

class SelectIfNullTest extends TestCase
{
    public function testEvaluateCondition(): void
    {
        $selectIfNull = new SelectIfNull('field1', 'true');
        $this->assertTrue($selectIfNull(['field1' => null], []));
        $this->assertEquals(2, $selectIfNull(['field1' => 2], []));

        $selectIfNull = new SelectIfNull('field1', 'field2');
        $this->assertEquals(25, $selectIfNull(['field1' => null, 'field2' => 25], []));

        $selectIfNull = new SelectIfNull('field1', 'test');
        $this->assertEquals('test', $selectIfNull(['field1' => null], []));
    }
}
