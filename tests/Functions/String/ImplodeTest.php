<?php

namespace Functions\String;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\String\Implode;
use PHPUnit\Framework\TestCase;

class ImplodeTest extends TestCase
{
    public function testImplodeDefaultSeparator(): void
    {
        $implode = new Implode('value');
        $this->assertSame('a,b', $implode(['value' => ['a', 'b']], []));
    }

    public function testImplodeCustomSeparator(): void
    {
        $implode = new Implode('value', ';');
        $this->assertSame('a;b', $implode(['value' => ['a', 'b']], []));
    }

    public function testImplodeScalarValue(): void
    {
        $implode = new Implode('value');
        $this->assertSame('123', $implode(['value' => 123], []));
    }

    public function testImplodeInvalidValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $implode = new Implode('value');
        $implode(['value' => new \stdClass()], []);
    }

    public function testToString(): void
    {
        $implode = new Implode('value');
        $this->assertSame('IMPLODE(value, ",")', (string) $implode);
    }
}
