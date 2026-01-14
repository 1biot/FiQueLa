<?php

namespace Functions\String;

use FQL\Functions\String\Replace;
use PHPUnit\Framework\TestCase;

class ReplaceTest extends TestCase
{
    public function testReplace(): void
    {
        $replace = new Replace('field', 'foo', 'bar');
        $result = $replace(['field' => 'foo baz foo'], []);
        $this->assertEquals('bar baz bar', $result);
    }

    public function testReplaceWithNoOccurrences(): void
    {
        $replace = new Replace('field', 'foo', 'bar');
        $result = $replace(['field' => 'baz qux'], []);
        $this->assertEquals('baz qux', $result);
    }

    public function testReplaceWithNullValue(): void
    {
        $replace = new Replace('field', 'foo', 'bar');
        $result = $replace(['field' => null], []);
        $this->assertEquals(null, $result);
    }

    public function testToString(): void
    {
        $replace = new Replace('field', 'foo', 'bar');
        $this->assertEquals('REPLACE(field, "foo", "bar")', (string) $replace);
    }

    public function testFieldAsText(): void
    {
        $replace = new Replace('"SQL Tutorial"', 'SQL', 'HTML');
        $result = $replace([], []);
        $this->assertEquals('HTML Tutorial', $result);
    }
}
