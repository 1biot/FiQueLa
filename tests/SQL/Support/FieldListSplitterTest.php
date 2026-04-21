<?php

namespace SQL\Support;

use FQL\Sql\Support\FieldListSplitter;
use PHPUnit\Framework\TestCase;

class FieldListSplitterTest extends TestCase
{
    public function testSplitsSimpleCommaList(): void
    {
        $this->assertSame(['id', 'name', 'price'], FieldListSplitter::split('id, name, price'));
    }

    public function testSplitsAcrossMultipleInputs(): void
    {
        // Variadic arguments are implicitly joined with `,` so they can be mixed freely.
        $this->assertSame(['a', 'b', 'c'], FieldListSplitter::split('a', 'b,c'));
    }

    public function testPreservesCommasInsideParentheses(): void
    {
        $this->assertSame(
            ['COUNT(a, b)', 'LOWER(name)'],
            FieldListSplitter::split('COUNT(a, b), LOWER(name)')
        );
    }

    public function testPreservesCommasInsideBrackets(): void
    {
        $this->assertSame(
            ['items[0,1]', 'name'],
            FieldListSplitter::split('items[0,1], name')
        );
    }

    public function testPreservesCommasInsideBackticks(): void
    {
        $spec = '`field with, comma`, second';
        $this->assertSame(['`field with, comma`', 'second'], FieldListSplitter::split($spec));
    }

    public function testPreservesCommasInsideDoubleQuotes(): void
    {
        $spec = 'CONCAT_WS(",", a, b), last';
        $this->assertSame(['CONCAT_WS(",", a, b)', 'last'], FieldListSplitter::split($spec));
    }

    public function testPreservesCommasInsideSingleQuotes(): void
    {
        $spec = "CONCAT_WS(',', a, b), last";
        $this->assertSame(["CONCAT_WS(',', a, b)", 'last'], FieldListSplitter::split($spec));
    }

    public function testStripsLeadingAndTrailingWhitespace(): void
    {
        $this->assertSame(['id', 'name'], FieldListSplitter::split('   id   ,   name   '));
    }

    public function testIgnoresEmptySegments(): void
    {
        $this->assertSame(['id'], FieldListSplitter::split('id,,'));
        $this->assertSame([], FieldListSplitter::split(''));
        $this->assertSame([], FieldListSplitter::split('   '));
    }

    public function testHandlesExoticFieldNames(): void
    {
        // Kebab-case, array-access brackets, arrow traversal, hash-prefixed names —
        // all things that the legacy tokenizer accepted but the new typed Tokenizer
        // rejects. The splitter uses a plain char-level scanner to stay tolerant.
        $this->assertSame(
            ['categories[]->name', 'order-total', '#hashField'],
            FieldListSplitter::split('categories[]->name, order-total, #hashField')
        );
    }

    public function testUnbalancedParensDontSwallowTrailingComma(): void
    {
        // Best-effort: if the caller hands us malformed input we still return something
        // usable. The closing paren under-depth cannot go negative.
        $this->assertSame(
            ['a)', 'b'],
            FieldListSplitter::split('a), b')
        );
    }

    public function testSplitAliasDetectsSimpleAS(): void
    {
        $parsed = FieldListSplitter::splitAlias('name AS n');
        $this->assertSame('name', $parsed['field']);
        $this->assertSame('n', $parsed['alias']);
    }

    public function testSplitAliasIsCaseInsensitive(): void
    {
        $parsed = FieldListSplitter::splitAlias('name as alias');
        $this->assertSame('alias', $parsed['alias']);
    }

    public function testSplitAliasStripsBackticksFromAlias(): void
    {
        $parsed = FieldListSplitter::splitAlias('name AS `some alias`');
        $this->assertSame('some alias', $parsed['alias']);
    }

    public function testSplitAliasReturnsNullWhenNoAS(): void
    {
        $parsed = FieldListSplitter::splitAlias('plain_field');
        $this->assertNull($parsed['alias']);
        $this->assertSame('plain_field', $parsed['field']);
    }

    public function testSplitAliasIgnoresASInsideParentheses(): void
    {
        // `CAST(x AS INT)` contains `AS` but at depth 1; it must not be picked up.
        $parsed = FieldListSplitter::splitAlias('CAST(x AS INT)');
        $this->assertNull($parsed['alias']);
        $this->assertSame('CAST(x AS INT)', $parsed['field']);
    }

    public function testSplitAliasIgnoresASInsideQuotes(): void
    {
        $parsed = FieldListSplitter::splitAlias('"literal AS value"');
        $this->assertNull($parsed['alias']);
    }

    public function testSplitAliasPrefersRightmostAS(): void
    {
        // `(a AS b)` is at depth 1 so ignored; `AS outer` is at depth 0 and wins.
        $parsed = FieldListSplitter::splitAlias('CAST(price AS INT) AS intPrice');
        $this->assertSame('CAST(price AS INT)', $parsed['field']);
        $this->assertSame('intPrice', $parsed['alias']);
    }
}
