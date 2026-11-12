<?php

namespace SQL\Parser;

use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Parser\Parser;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use PHPUnit\Framework\TestCase;

/**
 * `FROM *` addresses the root of an already-open stream (CSV / XLSX / ODS rows,
 * JSON top-level array). The bare `*` must parse as a FileQuery source after
 * FROM and DESCRIBE, while INTO and JOIN keep rejecting it.
 */
class FromStarSourceTest extends TestCase
{
    private function parse(string $sql): SelectStatementNode
    {
        return Parser::create()->parse(new TokenStream((new Tokenizer())->tokenize($sql)));
    }

    private function assertStarSource(SelectStatementNode $ast): FileQueryNode
    {
        $this->assertNotNull($ast->from);
        $source = $ast->from->source;
        $this->assertInstanceOf(FileQueryNode::class, $source);
        $this->assertSame('*', $source->raw);
        $this->assertNull($source->fileQuery->file);
        $this->assertNull($source->fileQuery->format);
        $this->assertSame('*', $source->fileQuery->query);
        return $source;
    }

    public function testSelectStarFromStar(): void
    {
        $ast = $this->parse('SELECT * FROM *');
        $source = $this->assertStarSource($ast);
        $this->assertSame(14, $source->position->offset);
        $this->assertNull($ast->from?->alias);
        $this->assertCount(1, $ast->fields);
        $this->assertInstanceOf(StarNode::class, $ast->fields[0]->expression);
    }

    public function testFromStarWithAlias(): void
    {
        $ast = $this->parse('SELECT * FROM * AS t');
        $this->assertStarSource($ast);
        $this->assertSame('t', $ast->from?->alias);
    }

    public function testFromStarWithWhereOrderByLimit(): void
    {
        $ast = $this->parse('SELECT name, price FROM * WHERE price > 100 ORDER BY price DESC LIMIT 5 OFFSET 2');
        $this->assertStarSource($ast);
        $this->assertNotNull($ast->where);
        $this->assertNotNull($ast->orderBy);
        $this->assertCount(1, $ast->orderBy->items);
        $this->assertNotNull($ast->limit);
        $this->assertSame(5, $ast->limit->limit);
        $this->assertSame(2, $ast->limit->offset);
    }

    public function testDescribeStar(): void
    {
        $ast = $this->parse('DESCRIBE *');
        $this->assertTrue($ast->describe);
        $this->assertStarSource($ast);
    }

    public function testIntoStarIsRejected(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected FILE_QUERY, got STAR ("*")');
        $this->parse('SELECT * FROM json(a.json).data INTO *');
    }

    public function testJoinStarIsRejected(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected FILE_QUERY, got STAR ("*")');
        $this->parse('SELECT * FROM json(a.json).data JOIN * AS j ON id = j.id');
    }

    public function testExistingStarSourcesStillParse(): void
    {
        $cases = [
            'SHOP.SHOPITEM' => ['file' => null, 'query' => 'SHOP.SHOPITEM'],
            'SHOP.*' => ['file' => null, 'query' => 'SHOP.*'],
            'csv(a.csv).*' => ['file' => 'a.csv', 'query' => '*'],
        ];
        foreach ($cases as $raw => $expected) {
            $source = $this->parse('SELECT * FROM ' . $raw)->from?->source;
            $this->assertInstanceOf(FileQueryNode::class, $source, $raw);
            $this->assertSame($raw, $source->raw, $raw);
            $this->assertSame($expected['file'], $source->fileQuery->file, $raw);
            $this->assertSame($expected['query'], $source->fileQuery->query, $raw);
        }
    }
}
