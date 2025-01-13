<?php

namespace Traits\Helpers;

use PHPUnit\Framework\TestCase;
use FQL\Traits\Helpers\StringOperations;

class StringOperationsTest extends TestCase
{
    use StringOperations;

    public function testCamelCaseToUpperSnakeCase(): void
    {
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('HelloWorld'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('helloWorld'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('Hello_World'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('hello_world'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('hello__world'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('Hello__World'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('hello___world'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('Hello___World'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('HelloWORLD'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('HELLO_WORLD'));
    }
}
