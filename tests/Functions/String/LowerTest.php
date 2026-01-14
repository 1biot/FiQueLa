<?php

namespace Functions\String;

use FQL\Functions\String\Lower;
use PHPUnit\Framework\TestCase;

class LowerTest extends TestCase
{
    public function testInvoke(): void
    {
        $lower = new Lower('value');
        $this->assertSame('hello', $lower(['value' => 'Hello'], []));
    }

    public function testInvokeCastsValue(): void
    {
        $lower = new Lower('value');
        $this->assertSame('123', $lower(['value' => 123], []));
    }

    public function testToString(): void
    {
        $lower = new Lower('value');
        $this->assertSame('LOWER(value)', (string) $lower);
    }
}
