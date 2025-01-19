<?php

namespace Functions\Hashing;

use FQL\Functions\Hashing\Sha1;
use PHPUnit\Framework\TestCase;

class Sha1Test extends TestCase
{
    public function testInvoke(): void
    {
        $sha1 = new Sha1('field');
        $item = ['field' => 'value'];
        $resultItem = [];
        $this->assertSame(sha1('value'), $sha1($item, $resultItem));
    }

    public function testInvokeWithNonString(): void
    {
        $sha1 = new Sha1('field');
        $item = ['field' => 123];
        $resultItem = [];
        $this->assertSame(sha1('123'), $sha1($item, $resultItem));
    }

    public function testInvokeWithNull(): void
    {
        $sha1 = new Sha1('field');
        $item = ['field' => null];
        $resultItem = [];
        $this->assertSame(sha1(''), $sha1($item, $resultItem));
    }

    public function testInvokeWithEmptyString(): void
    {
        $sha1 = new Sha1('field');
        $item = ['field' => ''];
        $resultItem = [];
        $this->assertSame(sha1(''), $sha1($item, $resultItem));
    }
}
