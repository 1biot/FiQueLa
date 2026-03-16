<?php

namespace Cli\Query;

use FQL\Cli\Query\QuerySplitter;
use PHPUnit\Framework\TestCase;

class QuerySplitterTest extends TestCase
{
    // -------------------------------------------------------
    // hasTerminatingSemicolon
    // -------------------------------------------------------

    public function testHasTerminatingSemicolonSimple(): void
    {
        $this->assertTrue(QuerySplitter::hasTerminatingSemicolon('SELECT * FROM items;'));
        $this->assertFalse(QuerySplitter::hasTerminatingSemicolon('SELECT * FROM items'));
    }

    public function testHasTerminatingSemicolonWithTrailingWhitespace(): void
    {
        $this->assertTrue(QuerySplitter::hasTerminatingSemicolon('SELECT * FROM items;  '));
    }

    public function testHasTerminatingSemicolonEmpty(): void
    {
        $this->assertFalse(QuerySplitter::hasTerminatingSemicolon(''));
        $this->assertFalse(QuerySplitter::hasTerminatingSemicolon('  '));
    }

    public function testHasTerminatingSemicolonInsideDoubleQuotes(): void
    {
        // Semicolon inside double quotes is NOT a terminator
        $this->assertFalse(QuerySplitter::hasTerminatingSemicolon(
            'SELECT * FROM [csv](file.csv, utf-8, ";"'
        ));
    }

    public function testHasTerminatingSemicolonInsideSingleQuotes(): void
    {
        // Semicolon inside single quotes is NOT a terminator
        $this->assertFalse(QuerySplitter::hasTerminatingSemicolon(
            "SELECT * FROM [csv](file.csv, utf-8, ';'"
        ));
    }

    public function testHasTerminatingSemicolonAfterQuotedSemicolon(): void
    {
        // There's a semicolon inside quotes AND a terminating one at the end
        $this->assertTrue(QuerySplitter::hasTerminatingSemicolon(
            'SELECT * FROM [csv](file.csv, utf-8, ";").*;'
        ));
    }

    public function testHasTerminatingSemicolonWithWhereClause(): void
    {
        $this->assertTrue(QuerySplitter::hasTerminatingSemicolon(
            "SELECT * FROM [csv](users.csv, utf-8, \";\").* WHERE name = 'O\\'Brien';"
        ));
    }

    // -------------------------------------------------------
    // split
    // -------------------------------------------------------

    public function testSplitSimple(): void
    {
        $result = QuerySplitter::split('SELECT 1; SELECT 2;');
        $this->assertEquals(['SELECT 1', 'SELECT 2'], $result);
    }

    public function testSplitSingleQuery(): void
    {
        $result = QuerySplitter::split('SELECT * FROM items;');
        $this->assertEquals(['SELECT * FROM items'], $result);
    }

    public function testSplitWithoutTrailingSemicolon(): void
    {
        $result = QuerySplitter::split('SELECT * FROM items');
        $this->assertEquals(['SELECT * FROM items'], $result);
    }

    public function testSplitWithQuotedSemicolon(): void
    {
        $result = QuerySplitter::split(
            'SELECT * FROM [csv](file.csv, utf-8, ";").*;'
        );
        $this->assertEquals(
            ['SELECT * FROM [csv](file.csv, utf-8, ";").*'],
            $result
        );
    }

    public function testSplitMultipleWithQuotedSemicolon(): void
    {
        $result = QuerySplitter::split(
            'SELECT * FROM [csv](a.csv, utf-8, ";").*; SELECT * FROM [csv](b.csv, utf-8, ";").*;'
        );
        $this->assertEquals([
            'SELECT * FROM [csv](a.csv, utf-8, ";").*',
            'SELECT * FROM [csv](b.csv, utf-8, ";").*',
        ], $result);

        $result2 = QuerySplitter::split(
            'SELECT * FROM [csv](a.csv, utf-8, ";").*; SELECT 1;'
        );
        $this->assertEquals([
            'SELECT * FROM [csv](a.csv, utf-8, ";").*',
            'SELECT 1',
        ], $result2);
    }

    public function testSplitEmpty(): void
    {
        $this->assertEquals([], QuerySplitter::split(''));
        $this->assertEquals([], QuerySplitter::split('  '));
        $this->assertEquals([], QuerySplitter::split(';'));
        $this->assertEquals([], QuerySplitter::split(';;;'));
    }

    public function testSplitWithSingleQuotes(): void
    {
        $result = QuerySplitter::split(
            "SELECT * FROM items WHERE name = 'test;value';"
        );
        $this->assertEquals(
            ["SELECT * FROM items WHERE name = 'test;value'"],
            $result
        );
    }

    // -------------------------------------------------------
    // stripTrailingSemicolon
    // -------------------------------------------------------

    public function testStripTrailingSemicolon(): void
    {
        $this->assertEquals(
            'SELECT * FROM items',
            QuerySplitter::stripTrailingSemicolon('SELECT * FROM items;')
        );
    }

    public function testStripTrailingSemicolonNoSemicolon(): void
    {
        $this->assertEquals(
            'SELECT * FROM items',
            QuerySplitter::stripTrailingSemicolon('SELECT * FROM items')
        );
    }

    public function testStripTrailingSemicolonWithSpaces(): void
    {
        $this->assertEquals(
            'SELECT * FROM items',
            QuerySplitter::stripTrailingSemicolon('SELECT * FROM items;  ')
        );
    }
}
