<?php

namespace Functions\Utils;

use FQL\Functions\Utils\SelectIf;
use PHPUnit\Framework\TestCase;

class SelectIfTest extends TestCase
{
    public function testEvaluateCondition(): void
    {
        $selectIfEqual = new SelectIf('field1 = 1', 'true', 'false');
        $this->assertTrue($selectIfEqual(['field1' => 1], []));
        $this->assertFalse($selectIfEqual(['field1' => 2], []));

        $selectIfEqualStrict = new SelectIf('field1 == "1"', 'true', 'false');
        $this->assertTrue($selectIfEqualStrict(['field1' => "1"], []));
        $this->assertFalse($selectIfEqualStrict(['field1' => 1], []));

        $selectIfEqualStrict = new SelectIf('field1 == 1', 'true', 'false');
        $this->assertTrue($selectIfEqualStrict(['field1' => 1], []));
        $this->assertFalse($selectIfEqualStrict(['field1' => "1"], []));
    }

    public function testEvaluateConditionTrueStatement(): void
    {
        $selectIfEqual = new SelectIf('field1 = 1', 'field2', 'null');
        $this->assertEquals(1000, $selectIfEqual(['field1' => 1, 'field2' => 1000], []));

        $selectIfEqual = new SelectIf('field1 != 1', 'field2', 'null');
        $this->assertEquals(null, $selectIfEqual(['field1' => 1, 'field2' => 1000], []));
    }
}
