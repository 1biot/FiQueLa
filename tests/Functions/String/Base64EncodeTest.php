<?php

namespace Functions\String;

use FQL\Functions\String\Base64Encode;
use PHPUnit\Framework\TestCase;

class Base64EncodeTest extends TestCase
{
    public function testInvoke(): void
    {
        $encode = new Base64Encode('value');
        $this->assertSame(base64_encode('hello'), $encode(['value' => 'hello'], []));
    }

    public function testInvokeCastsValue(): void
    {
        $encode = new Base64Encode('value');
        $this->assertSame(base64_encode('123'), $encode(['value' => 123], []));
    }

    public function testToString(): void
    {
        $encode = new Base64Encode('value');
        $this->assertSame('BASE64_ENCODE(value)', (string) $encode);
    }
}
