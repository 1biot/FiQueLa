<?php

namespace SQL\Ast;

use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use FQL\Enum\Sort;
use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\CaseExpressionNode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\CollectObjectExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Compiler;
use FQL\Sql\Lint\AstWalker;
use FQL\Sql\Token\Span;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

class NodeSpanTest extends TestCase
{
    private const SQL = <<<'FQL'
        -- report
        WITH cheap AS (SELECT id FROM json(a.json) WHERE (price < 10 OR sale = true))
        SELECT DISTINCT p.name AS `Název`, COUNT(*) /* all */, price * 2 + 1,
            CASE WHEN x > 1 THEN "a" ELSE 'b' END, CAST(y AS INT),
            MATCH(title) AGAINST("kolo IN NATURAL MODE"), COLLECT_OBJECT(id, name AS n ORDER BY name DESC)
            EXCLUDE secret
        FROM cheap AS p
        LEFT OUTER JOIN (SELECT id FROM csv(b.csv, delimiter: ";")) AS b ON b.id = p.id
        WHERE x IN (1, 2) AND y BETWEEN 1 AND 3 AND z IS NOT NULL
        GROUP BY p.name
        HAVING cnt > 1
        ORDER BY p.name DESC, id
        LIMIT 10 OFFSET 5
        INTO csv(./out.csv)
        UNION ALL SELECT name FROM json(c.json)
        FQL;

    public function testEveryParsedNodeHasASpanInsideTheStatement(): void
    {
        $ast = (new Compiler(self::SQL))->toAst();
        $root = $ast->span;
        $this->assertNotNull($root);
        $this->assertSame(strpos(self::SQL, 'WITH'), $root->start);
        $this->assertSame(strlen(self::SQL), $root->end);

        foreach (AstWalker::findAll($ast, AstNode::class) as $node) {
            $this->assertInstanceOf(HasSpan::class, $node);
            $span = $node->span();
            $this->assertNotNull($span, $node::class . ' has no span');
            $this->assertTrue($root->contains($span), $node::class . ' lies outside the statement');
            $text = $span->slice(self::SQL);
            $this->assertSame(trim($text), $text, $node::class . ' span includes surrounding whitespace');
        }
    }

    public function testSpansCoverTheNodeText(): void
    {
        $ast = (new Compiler(self::SQL))->toAst();
        $slice = static fn (?HasSpan $node): ?string => $node?->span()?->slice(self::SQL);

        $this->assertSame('cheap AS (SELECT id FROM json(a.json) WHERE (price < 10 OR sale = true))', $slice($ast->commonTables[0]));
        $this->assertSame('p.name AS `Název`', $slice($ast->fields[0]));
        $this->assertSame('COUNT(*)', $slice($ast->fields[1]));
        $this->assertSame('price * 2 + 1', $slice($ast->fields[2]));
        $this->assertSame('secret', $slice($ast->fields[7]));
        $this->assertSame('FROM cheap AS p', $slice($ast->from));
        $this->assertSame('LEFT OUTER JOIN (SELECT id FROM csv(b.csv, delimiter: ";")) AS b ON b.id = p.id', $slice($ast->joins[0]));
        $this->assertSame('(SELECT id FROM csv(b.csv, delimiter: ";"))', $slice($ast->joins[0]->source instanceof SubQueryNode ? $ast->joins[0]->source : null));
        $this->assertSame('WHERE x IN (1, 2) AND y BETWEEN 1 AND 3 AND z IS NOT NULL', $slice($ast->where));
        $this->assertSame('x IN (1, 2) AND y BETWEEN 1 AND 3 AND z IS NOT NULL', $slice($ast->where?->conditions));
        $this->assertSame('GROUP BY p.name', $slice($ast->groupBy));
        $this->assertSame('HAVING cnt > 1', $slice($ast->having));
        $this->assertSame('ORDER BY p.name DESC, id', $slice($ast->orderBy));
        $this->assertSame('p.name DESC', $slice($ast->orderBy?->items[0]));
        $this->assertSame('LIMIT 10 OFFSET 5', $slice($ast->limit));
        $this->assertSame('INTO csv(./out.csv)', $slice($ast->into));
        $this->assertSame('csv(./out.csv)', $slice($ast->into?->target));
        $this->assertSame('UNION ALL SELECT name FROM json(c.json)', $slice($ast->unions[0]));
        $this->assertSame('SELECT name FROM json(c.json)', $slice($ast->unions[0]->query));
    }

    public function testExpressionSpans(): void
    {
        $ast = (new Compiler(self::SQL))->toAst();
        $slice = static fn (?HasSpan $node): ?string => $node?->span()?->slice(self::SQL);
        $first = static function (string $class) use ($ast): HasSpan {
            foreach (AstWalker::findAll($ast, $class) as $node) {
                if ($node instanceof HasSpan) {
                    return $node;
                }
            }
            throw new \RuntimeException('no ' . $class);
        };

        $this->assertSame('price * 2 + 1', $slice($first(BinaryOpNode::class)));
        $this->assertSame('CASE WHEN x > 1 THEN "a" ELSE \'b\' END', $slice($first(CaseExpressionNode::class)));
        $this->assertSame('CAST(y AS INT)', $slice($first(CastExpressionNode::class)));
        $this->assertSame('MATCH(title) AGAINST("kolo IN NATURAL MODE")', $slice($first(MatchAgainstNode::class)));
        $this->assertSame('id, name AS n ORDER BY name DESC', $slice($first(CollectObjectExpressionNode::class)));
        $cteFrom = $ast->commonTables[0]->query->from?->source;
        $this->assertInstanceOf(FileQueryNode::class, $cteFrom);
        $this->assertSame('json(a.json)', $slice($cteFrom));
        $this->assertSame('x > 1', $slice($first(ConditionExpressionNode::class)));

        $cte = $ast->commonTables[0]->query->where?->conditions;
        $this->assertInstanceOf(ConditionGroupNode::class, $cte);
        $this->assertSame('(price < 10 OR sale = true)', $slice($cte));
        $nested = $cte->entries[0]['condition'];
        $this->assertSame('(price < 10 OR sale = true)', $slice($nested));
        $this->assertGreaterThan(
            $nested->span()?->start,
            $nested instanceof ConditionGroupNode ? $nested->entries[0]['condition']->span()?->start : null,
            'a nested group spans its parentheses'
        );

        $collect = $first(FunctionCallNode::class);
        $this->assertSame('COUNT(*)', $slice($collect));
    }

    public function testLimitAndOffsetSpans(): void
    {
        $span = static fn (string $sql): ?string => (new Compiler($sql))->toAst()->limit?->span()?->slice($sql);

        $this->assertSame('LIMIT 5, 10', $span('SELECT a FROM json(a.json) LIMIT 5, 10'));
        $this->assertSame('OFFSET 5 LIMIT 10', $span('SELECT a FROM json(a.json) OFFSET 5 LIMIT 10'));
        $this->assertSame('OFFSET 5', $span('SELECT a FROM json(a.json) OFFSET 5'));
        // Split by another clause: only the part parsed last.
        $this->assertSame('OFFSET 5', $span('SELECT a FROM json(a.json) LIMIT 10 ORDER BY a OFFSET 5'));
    }

    public function testEmptyWhereHasAnEmptySpan(): void
    {
        $sql = 'SELECT a FROM json(a.json) WHERE ORDER BY a';
        $where = (new Compiler($sql))->toAst()->where;

        $this->assertSame('WHERE', $where?->span()?->slice($sql));
        $this->assertTrue($where?->conditions->span()?->isEmpty());
    }

    public function testEveryNodeClassImplementsHasSpan(): void
    {
        foreach (array_merge(glob(__DIR__ . '/../../../src/Sql/Ast/Node/*.php') ?: [], glob(__DIR__ . '/../../../src/Sql/Ast/Expression/*.php') ?: []) as $file) {
            $class = 'FQL\\Sql\\Ast\\' . basename(dirname($file)) . '\\' . basename($file, '.php');
            $reflection = new \ReflectionClass($class);
            if ($reflection->isInterface() || $reflection->isEnum()) {
                continue;
            }
            $this->assertTrue($reflection->implementsInterface(HasSpan::class), $class);
            $parameters = $reflection->getConstructor()?->getParameters() ?? [];
            $last = $parameters[count($parameters) - 1];
            $this->assertSame('span', $last->getName(), $class);
            $this->assertTrue($last->isOptional(), $class);
        }
    }

    public function testWithHelpersReturnCopiesWithoutSpan(): void
    {
        $ast = (new Compiler(self::SQL))->toAst();
        $copy = $ast->withDistinct(false);

        $this->assertNotSame($ast, $copy);
        $this->assertNull($copy->span);
        $this->assertFalse($copy->distinct);
        $this->assertSame($ast->fields, $copy->fields);
        $this->assertSame($ast->where, $copy->where);
        $this->assertSame($ast->commonTables, $copy->commonTables);

        $item = $ast->orderBy?->items[0];
        $this->assertNotNull($item);
        $flipped = $item->withDirection(Sort::ASC);
        $this->assertSame($item->expression, $flipped->expression);
        $this->assertNull($flipped->span);

        $statement = new \ReflectionClass(SelectStatementNode::class);
        foreach (['From', 'Fields', 'Distinct', 'Joins', 'Where', 'GroupBy', 'Having', 'OrderBy', 'Limit', 'Unions', 'Into', 'Explain', 'CommonTables'] as $name) {
            $this->assertTrue($statement->hasMethod('with' . $name), 'with' . $name);
        }
    }

    public function testConditionGroupHelpers(): void
    {
        $sql = 'SELECT a FROM json(a.json) WHERE a = 1 OR b = 2';
        $group = (new Compiler($sql))->toAst()->where?->conditions;
        $this->assertNotNull($group);
        $extra = new ConditionExpressionNode(new ColumnReferenceNode('c', $group->position), Operator::EQUAL, new ColumnReferenceNode('d', $group->position), $group->position);

        $appended = $group->withAppended(LogicalOperator::XOR, $extra);
        $this->assertCount(3, $appended->entries);
        $this->assertSame(LogicalOperator::XOR, $appended->entries[2]['logical']);
        $this->assertSame($extra, $appended->entries[2]['condition']);

        $removed = $group->withoutEntry(0);
        $this->assertCount(1, $removed->entries);
        $this->assertSame(LogicalOperator::AND, $removed->entries[0]['logical']);
        $this->assertSame($group->entries[1]['condition'], $removed->entries[0]['condition']);

        $this->expectException(\OutOfRangeException::class);
        $group->withoutEntry(5);
    }

    public function testTokenStreamSpanHelpers(): void
    {
        $stream = new TokenStream((new Tokenizer())->tokenize('SELECT  a /* x */ , b'));
        $this->assertNull($stream->previous());
        $select = $stream->consume();
        $stream->consume();
        $this->assertSame(TokenType::IDENTIFIER, $stream->previous()?->type);
        $this->assertSame('SELECT  a', $stream->spanFrom($select)->slice('SELECT  a /* x */ , b'));
        $this->assertTrue($stream->spanFrom(20)->isEmpty());
    }

    public function testSpanValueObject(): void
    {
        $span = new Span(2, 5);
        $this->assertSame(3, $span->length());
        $this->assertSame('cde', $span->slice('abcdefg'));
        $this->assertTrue($span->contains(new Span(3, 5)));
        $this->assertFalse($span->contains(new Span(1, 5)));
        $this->assertSame('[2, 5)', (string) $span);

        $this->expectException(\InvalidArgumentException::class);
        new Span(5, 2);
    }
}
