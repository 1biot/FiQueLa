<?php

namespace Functions\String;

use FQL\Functions\String\LeftPad;
use PHPUnit\Framework\TestCase;

class LeftPadTest extends TestCase
{
    public function testLeftPad(): void
    {
        $leftPad = new LeftPad('field', 10, '0');
        $result = $leftPad(['field' => '123'], []);
        $this->assertEquals('0000000123', $result);

        $leftPadString = (string) $leftPad;
        $this->assertEquals('LPAD(field, 10, "0")', $leftPadString);

        $leftPad = new LeftPad('field', 5, '+');
        $result = $leftPad(['field' => '123'], []);
        $this->assertEquals('++123', $result);

        $leftPadString = (string) $leftPad;
        $this->assertEquals('LPAD(field, 5, "+")', $leftPadString);

        $leftPad = new LeftPad('field', 3);
        $result = $leftPad(['field' => '123'], []);
        $this->assertEquals('123', $result);

        $leftPadString = (string) $leftPad;
        $this->assertEquals('LPAD(field, 3, " ")', $leftPadString);

        $leftPad = new LeftPad('field', 8);
        $result = $leftPad(['field' => '123'], []);
        $this->assertEquals('     123', $result);

        $leftPadString = (string) $leftPad;
        $this->assertEquals('LPAD(field, 8, " ")', $leftPadString);

        $leftPad = new LeftPad('field', 2, '0');
        $result = $leftPad(['field' => '123'], []);
        $this->assertEquals('123', $result);

        $leftPadString = (string) $leftPad;
        $this->assertEquals('LPAD(field, 2, "0")', $leftPadString);

        $leftPad = new LeftPad('field', 10, 'AB');
        $result = $leftPad(['field' => '123'], []);
        $this->assertEquals('ABABABA123', $result);

        $leftPadString = (string) $leftPad;
        $this->assertEquals('LPAD(field, 10, "AB")', $leftPadString);
    }

    public function testLeftPadWithNullValue(): void
    {
        $leftPad = new LeftPad('field', 10, '0');
        $result = $leftPad(['field' => null], []);
        $this->assertEquals('0000000000', $result);

        $leftPadString = (string) $leftPad;
        $this->assertEquals('LPAD(field, 10, "0")', $leftPadString);
    }

    public function testLeftPadWithNegativeLength(): void
    {
        $leftPad = new LeftPad('field', -5, '0');
        $result = $leftPad(['field' => '123'], []);
        $this->assertEquals('123', $result);

        $leftPadString = (string) $leftPad;
        $this->assertEquals('LPAD(field, -5, "0")', $leftPadString);
    }
}
