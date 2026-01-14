<?php

namespace Functions\String;

use FQL\Functions\String\Upper;
use PHPUnit\Framework\TestCase;

class UpperTest extends TestCase
{
    public function testInvoke(): void
    {
        $upper = new Upper('value');
        $this->assertSame('HELLO', $upper(['value' => 'Hello'], []));
    }

    public function testInvokeCastsValue(): void
    {
        $upper = new Upper('value');
        $this->assertSame('123', $upper(['value' => 123], []));
    }

    public function testToString(): void
    {
        $upper = new Upper('value');
        $this->assertSame('UPPER(value)', (string) $upper);
    }
}
