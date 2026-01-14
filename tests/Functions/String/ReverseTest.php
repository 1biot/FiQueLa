<?php

namespace Functions\String;

use FQL\Functions\String\Reverse;
use PHPUnit\Framework\TestCase;

class ReverseTest extends TestCase
{
    public function testInvoke(): void
    {
        $reverse = new Reverse('value');
        $this->assertSame('cba', $reverse(['value' => 'abc'], []));
    }

    public function testInvokeCastsValue(): void
    {
        $reverse = new Reverse('value');
        $this->assertSame('321', $reverse(['value' => 123], []));
    }

    public function testToString(): void
    {
        $reverse = new Reverse('value');
        $this->assertSame('REVERSE(value)', (string) $reverse);
    }
}
