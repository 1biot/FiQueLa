<?php

namespace Functions\Utils;

use FQL\Functions\Utils\SelectCase;
use PHPUnit\Framework\TestCase;

class SelectCaseTest extends TestCase
{
    public function testSelectCase(): void
    {
        $selectCase = new SelectCase();
        $selectCase->addCondition('value = case1', 'result1');
        $selectCase->addCondition('value2 = case2', 'result2');

        $result = $selectCase(['value' => 'case1', 'result1' => 1, 'value2' => 'case2', 'result2' => 2], []);
        $this->assertEquals(1, $result);

        $result = $selectCase(['value' => 'case3', 'result1' => 1, 'value2' => 'case2', 'result2' => 2], []);
        $this->assertEquals(2, $result);

        $this->assertEquals(
            "CASE WHEN value = 'case1' THEN result1 WHEN value2 = 'case2' THEN result2 END",
            (string) $selectCase
        );

        $selectCase->addDefault('2025');

        $result = $selectCase(['value' => 'case3', 'result1' => 1, 'value2' => 'case4', 'result2' => 2], []);
        $this->assertEquals(2025, $result);

        $this->assertEquals(
            "CASE WHEN value = 'case1' THEN result1 WHEN value2 = 'case2' THEN result2 ELSE 2025 END",
            (string) $selectCase
        );
    }
}
