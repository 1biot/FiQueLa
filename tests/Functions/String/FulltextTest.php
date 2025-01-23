<?php

namespace Functions\String;

use FQL\Enum;
use FQL\Functions;
use PHPUnit\Framework\TestCase;

class FulltextTest extends TestCase
{
    public function testInvoke(): void
    {
        $function = new Functions\String\Fulltext(['bar', 'foo'], 'bar');
        $this->assertSame(1.0, $function->__invoke(['foo' => 'foo', 'bar' => 'bar'], []));
    }

    public function testToString(): void
    {
        $function = new Functions\String\Fulltext(['foo'], 'bar');
        $this->assertSame('MATCH(foo) AGAINST("bar" IN NATURAL MODE)', $function->__toString());

        $function = new Functions\String\Fulltext(['foo'], 'bar', Enum\Fulltext::BOOLEAN);
        $this->assertSame('MATCH(foo) AGAINST("bar" IN BOOLEAN MODE)', $function->__toString());
    }

    public function testScoreNatural(): void
    {
        $function = new Functions\String\Fulltext(['foo', 'bar'], 'bar Value Does Not Exists');
        $this->assertSame(0.0, $function->__invoke(['foo' => 'fooValue', 'bar' => 'barValue'], []));

        $function = new Functions\String\Fulltext(['foo', 'bar'], 'barValue');
        $this->assertSame(0.5, $function->__invoke(['foo' => 'fooValue', 'bar' => 'barValue'], []));

        $function = new Functions\String\Fulltext(['bar', 'foo'], 'barValue');
        $this->assertSame(1.0, $function->__invoke(['foo' => 'fooValue', 'bar' => 'barValue'], []));

        $function = new Functions\String\Fulltext(['foo', 'bar'], 'bar Value');
        $this->assertSame(5.5, $function->__invoke(['foo' => 'foo bar', 'bar' => 'bar value'], []));

        $function = new Functions\String\Fulltext(['bar', 'foo'], 'value barValue');
        $this->assertSame(8.0, $function->__invoke(['foo' => 'foo bar value', 'bar' => 'Value barValue'], []));
    }

    public function testScoreBoolean(): void
    {
        $function = new Functions\String\Fulltext(['foo', 'bar'], 'barValue', Enum\Fulltext::BOOLEAN);
        $this->assertSame(0.5, $function->__invoke(['foo' => 'fooValue', 'bar' => 'barValue'], []));

        $function = new Functions\String\Fulltext(['bar', 'foo'], 'barValue', Enum\Fulltext::BOOLEAN);
        $this->assertSame(1.0, $function->__invoke(['foo' => 'fooValue', 'bar' => 'barValue'], []));

        $function = new Functions\String\Fulltext(['foo', 'bar'], 'bar Value', Enum\Fulltext::BOOLEAN);
        $this->assertSame(2.0, $function->__invoke(['foo' => 'foo bar', 'bar' => 'bar value'], []));

        $function = new Functions\String\Fulltext(['bar', 'foo'], 'value barValue', Enum\Fulltext::BOOLEAN);
        $this->assertSame(2.5, $function->__invoke(['foo' => 'foo bar value', 'bar' => 'Value barValue'], []));

        $function = new Functions\String\Fulltext(['bar', 'foo'], '+value +barValue', Enum\Fulltext::BOOLEAN);
        $this->assertSame(2.5, $function->__invoke(['foo' => 'foo bar value', 'bar' => 'Value barValue'], []));

        $function = new Functions\String\Fulltext(['bar', 'foo'], '-value +bar -Value', Enum\Fulltext::BOOLEAN);
        $this->assertSame(1.0, $function->__invoke(['foo' => 'foo value', 'bar' => 'Value barValue'], []));
    }
}
