<?php

namespace Functions\String;

use FQL\Functions\String\RandomString;
use PHPUnit\Framework\TestCase;

class RandomStringTest extends TestCase
{
    public function testRandomStringDefault(): void
    {
        $random = new RandomString();
        $value = $random();
        $this->assertSame(10, strlen($value));
        $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $this->assertSame(strlen($value), strspn($value, $charset));
        $this->assertSame('RANDOM_STRING(10)', (string) $random);
    }

    public function testRandomStringCustomLength(): void
    {
        $random = new RandomString(16);
        $value = $random();
        $this->assertSame(16, strlen($value));
        $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $this->assertSame(strlen($value), strspn($value, $charset));
        $this->assertSame('RANDOM_STRING(16)', (string) $random);
    }

    public function testRandomStringInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $random = new RandomString(0);
        $random();
    }
}
