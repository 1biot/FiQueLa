<?php

namespace Functions\String;

use FQL\Functions\String\RightPad;
use PHPUnit\Framework\TestCase;

class RightPadTest extends TestCase
{
    public function testRightPad(): void
    {
        $rightPad = new RightPad('field', 10, '0');
        $result = $rightPad(['field' => '123'], []);
        $this->assertEquals('1230000000', $result);

        $rightPadString = (string) $rightPad;
        $this->assertEquals('RPAD(field, 10, "0")', $rightPadString);

        $rightPad = new RightPad('field', 5, '+');
        $result = $rightPad(['field' => '123'], []);
        $this->assertEquals('123++', $result);

        $rightPadString = (string) $rightPad;
        $this->assertEquals('RPAD(field, 5, "+")', $rightPadString);

        $rightPad = new RightPad('field', 3);
        $result = $rightPad(['field' => '123'], []);
        $this->assertEquals('123', $result);

        $rightPadString = (string) $rightPad;
        $this->assertEquals('RPAD(field, 3, " ")', $rightPadString);

        $rightPad = new RightPad('field', 8);
        $result = $rightPad(['field' => '123'], []);
        $this->assertEquals('123     ', $result);

        $rightPadString = (string) $rightPad;
        $this->assertEquals('RPAD(field, 8, " ")', $rightPadString);

        $rightPad = new RightPad('field', 2, '0');
        $result = $rightPad(['field' => '123'], []);
        $this->assertEquals('123', $result);

        $rightPadString = (string) $rightPad;
        $this->assertEquals('RPAD(field, 2, "0")', $rightPadString);

        $rightPad = new RightPad('field', 10, 'AB');
        $result = $rightPad(['field' => '123'], []);
        $this->assertEquals('123ABABABA', $result);

        $rightPadString = (string) $rightPad;
        $this->assertEquals('RPAD(field, 10, "AB")', $rightPadString);
    }

    public function testRightPadWithNullValue(): void
    {
        $rightPad = new RightPad('field', 10, '0');
        $result = $rightPad(['field' => null], []);
        $this->assertEquals('0000000000', $result);

        $rightPadString = (string) $rightPad;
        $this->assertEquals('RPAD(field, 10, "0")', $rightPadString);
    }

    public function testRightPadWithNegativeLength(): void
    {
        $rightPad = new RightPad('field', -5, '0');
        $result = $rightPad(['field' => '123'], []);
        $this->assertEquals('123', $result);

        $rightPadString = (string) $rightPad;
        $this->assertEquals('RPAD(field, -5, "0")', $rightPadString);
    }
}
