<?php

namespace Functions\String;

use FQL\Functions\String\Substring;
use PHPUnit\Framework\TestCase;

class SubstringTest extends TestCase
{
    public function testSubstring(): void
    {
        $substring = new Substring('field', 1, 3);
        $result = $substring(['field' => 'abcdefg'], []);
        $this->assertEquals('bcd', $result);

        $substringWithoutLength = new Substring('field', 2);
        $resultWithoutLength = $substringWithoutLength(['field' => 'abcdefg'], []);
        $this->assertEquals('cdefg', $resultWithoutLength);

        $substringWithNullValue = new Substring('field', 1, 3);
        $resultWithNullValue = $substringWithNullValue(['field' => null], []);
        $this->assertEquals(null, $resultWithNullValue);

        $substringString = (string) $substring;
        $this->assertEquals('SUBSTRING(field, 1, 3)', $substringString);

        $substringWithoutLengthString = (string) $substringWithoutLength;
        $this->assertEquals('SUBSTRING(field, 2)', $substringWithoutLengthString);
    }
}
