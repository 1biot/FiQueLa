<?php

namespace Functions\String;

use FQL\Functions\String\Base64Decode;
use PHPUnit\Framework\TestCase;

class Base64DecodeTest extends TestCase
{
    public function testInvoke(): void
    {
        $decode = new Base64Decode('value');
        $this->assertSame('hello', $decode(['value' => base64_encode('hello')], []));
    }

    public function testInvokeCastsValue(): void
    {
        $decode = new Base64Decode('value');
        $this->assertSame('test', $decode(['value' => base64_encode('test')], []));
    }

    public function testToString(): void
    {
        $decode = new Base64Decode('value');
        $this->assertSame('BASE64_DECODE(value)', (string) $decode);
    }
}
