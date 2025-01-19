<?php

namespace Functions\Hashing;

use FQL\Functions\Hashing\Md5;
use PHPUnit\Framework\TestCase;

class Md5Test extends TestCase
{
    public function testInvoke(): void
    {
        $md5 = new Md5('field');
        $item = ['field' => 'value'];
        $resultItem = [];
        $this->assertSame(md5('value'), $md5($item, $resultItem));
    }

    public function testInvokeWithNonString(): void
    {
        $md5 = new Md5('field');
        $item = ['field' => 123];
        $resultItem = [];
        $this->assertSame(md5('123'), $md5($item, $resultItem));
    }

    public function testInvokeWithNull(): void
    {
        $md5 = new Md5('field');
        $item = ['field' => null];
        $resultItem = [];
        $this->assertSame(md5(''), $md5($item, $resultItem));
    }

    public function testInvokeWithEmptyString(): void
    {
        $md5 = new Md5('field');
        $item = ['field' => ''];
        $resultItem = [];
        $this->assertSame(md5(''), $md5($item, $resultItem));
    }
}
