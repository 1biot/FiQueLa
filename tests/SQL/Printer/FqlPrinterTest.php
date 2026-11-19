<?php

namespace SQL\Printer;

use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use FQL\Enum\Sort;
use FQL\Enum\Type;
use FQL\Query\FileQuery;
use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\BinaryOperator;
use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\WholeRowNode;
use FQL\Sql\Ast\Node\GroupByClauseNode;
use FQL\Sql\Ast\Node\OrderByItemNode;
use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Compiler;
use FQL\Sql\Printer\FqlPrinter;
use FQL\Sql\Printer\KeywordCase;
use FQL\Sql\Printer\UnprintableNodeException;
use FQL\Sql\Token\Position;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AstShape.php';

class FqlPrinterTest extends TestCase
{
    public function testIdentifiersAreBacktickedOnlyWhenNeeded(): void
    {
        $this->assertSame('price', FqlPrinter::identifier('price'));
        $this->assertSame('a.b', FqlPrinter::identifier('a.b'));
        $this->assertSame('`Kód objednávky`', FqlPrinter::identifier('Kód objednávky'));
        $this->assertSame('`select`', FqlPrinter::identifier('select'));
        $this->assertSame('`TRUE`', FqlPrinter::identifier('TRUE'));
        $this->assertSame('`1st`', FqlPrinter::identifier('1st'));
    }

    public function testColumnPathsAreQuotedSegmentBySegment(): void
    {
        $this->assertSame('items[].name', FqlPrinter::columnReference('items[].name'));
        $this->assertSame('p.*', FqlPrinter::columnReference('p.*'));
        $this->assertSame('`my col`', FqlPrinter::columnReference('my col'));
        $this->assertSame('`Kód`.items[].`Název zboží`', FqlPrinter::columnReference('Kód.items[].Název zboží'));
        $this->assertSame('`order`.id', FqlPrinter::columnReference('order.id'));
        $this->assertSame('`abc`', FqlPrinter::columnReference('abc', quoted: true));
        $this->assertSame('`a`.`b c`', FqlPrinter::columnReference('`a`.`b c`', quoted: true));
    }

    public function testStringsUseDoubleQuotesUnlessTheValueHasOne(): void
    {
        $this->assertSame('"Škoda"', FqlPrinter::stringLiteral('Škoda'));
        $this->assertSame("'say \"hi\"'", FqlPrinter::stringLiteral('say "hi"'));
        $this->expectException(UnprintableNodeException::class);
        FqlPrinter::stringLiteral('it\'s "x"');
    }

    public function testBacktickedIdentifiersSurviveFreshPrinting(): void
    {
        $sql = 'SELECT `my col`, `Kód` AS `Kód zboží`, `a`.`b c` FROM json(a.json) WHERE `my col` = `Kód`';

        $this->assertSame($sql, (new FqlPrinter())->print((new Compiler($sql))->toAst()));
    }

    public function testLiteralsKeepTheirSpelling(): void
    {
        $sql = "SELECT a FROM json(a.json) WHERE a = \"x\" AND b = 'y' AND c = TRUE AND d = false AND e = 1.50 AND f = -3";

        $this->assertSame($sql, (new FqlPrinter())->print((new Compiler($sql))->toAst()));
    }

    /**
     * @return array<string, array{ExpressionNode, string}>
     */
    public static function literals(): array
    {
        $p = Position::synthetic();
        return [
            'string' => [new LiteralNode('Praha', Type::STRING, '', $p), '"Praha"'],
            'string with double quote' => [new LiteralNode('a "b"', Type::STRING, '', $p), "'a \"b\"'"],
            'integer' => [new LiteralNode(-42, Type::INTEGER, '', $p), '-42'],
            'float' => [new LiteralNode(1.5, Type::FLOAT, '', $p), '1.5'],
            'whole float' => [new LiteralNode(2.0, Type::FLOAT, '', $p), '2.0'],
            'tiny float' => [new LiteralNode(1.0E-7, Type::FLOAT, '', $p), '0.0000001'],
            'true' => [new LiteralNode(true, Type::BOOLEAN, '', $p), 'TRUE'],
            'null' => [new LiteralNode(null, Type::NULL, '', $p), 'NULL'],
            'stale raw' => [new LiteralNode('new', Type::STRING, '"old"', $p), '"new"'],
            'numeric string' => [new LiteralNode('5', Type::STRING, '', $p), '"5"'],
        ];
    }

    #[DataProvider('literals')]
    public function testLiteralsAreWrittenFromTheirValue(ExpressionNode $literal, string $expected): void
    {
        $this->assertSame($expected, (new FqlPrinter())->print($literal));
    }

    public function testConditionsKeepColumnsAndOperators(): void
    {
        $sql = 'SELECT a FROM json(a.json) WHERE a = `b` AND c != d.e AND f IN (1, "x", g) AND h NOT BETWEEN 1 AND 2'
            . ' AND i IS NOT NULL AND j IS NUMBER AND k NOT LIKE "%x" AND l REGEXP "^a" AND m <> 1';
        $ast = (new Compiler($sql))->toAst();

        $printed = (new FqlPrinter())->print($ast);

        $this->assertSame(str_replace('m <> 1', 'm != 1', $sql), $printed);
    }

    public function testArithmeticIsWrittenWithOperatorsAndPrecedence(): void
    {
        $sql = 'SELECT price * 2 + 1, a - b - c, a + b * c, a / b % c FROM json(a.json)';

        $this->assertSame($sql, (new FqlPrinter())->print((new Compiler($sql))->toAst()));
    }

    public function testOrderByWritesOnlyDescending(): void
    {
        $sql = 'SELECT a FROM json(a.json) ORDER BY a ASC, b DESC, c';

        $this->assertSame(
            'SELECT a FROM json(a.json) ORDER BY a, b DESC, c',
            (new FqlPrinter())->print((new Compiler($sql))->toAst())
        );
    }

    public function testKeywordCaseIsConfigurable(): void
    {
        $sql = 'SELECT COUNT(*) AS n FROM json(a.json) WHERE a IS NULL AND b = TRUE GROUP BY c ORDER BY n DESC LIMIT 5 OFFSET 1';
        $printer = new FqlPrinter(KeywordCase::LOWER);

        $this->assertSame(KeywordCase::LOWER, $printer->keywordCase());
        $this->assertSame(
            'select count(*) as n from json(a.json) where a is null and b = TRUE group by c order by n desc limit 5 offset 1',
            $printer->print((new Compiler($sql))->toAst())
        );
    }

    public function testFileQueryIsWrittenWithEveryNonDefaultParameter(): void
    {
        $p = Position::synthetic();
        $fileQuery = (new FileQuery('csv(./a.csv)'))->withParams(['delimiter' => ';', 'enclosure' => "'", 'bom' => '1']);

        $this->assertSame(
            'csv(./a.csv, delimiter: ";", enclosure: "\'", bom: "1")',
            (new FqlPrinter())->print(new FileQueryNode($fileQuery, '', $p))
        );
        $this->assertSame(
            'csv(./a.csv, "utf-8", ";")',
            (new FqlPrinter())->print(new FileQueryNode(new FileQuery('csv(./a.csv, delimiter: ";")'), '', $p))
        );
        $this->assertSame(
            'csv(./a.csv, delimiter: ";")',
            (new FqlPrinter())->print(new FileQueryNode(new FileQuery('csv(./a.csv, delimiter: ";")'), 'csv(./a.csv, delimiter: ";")', $p))
        );
    }

    public function testCastAndFunctionsAreWrittenFresh(): void
    {
        $p = Position::synthetic();
        $cast = new CastExpressionNode(new ColumnReferenceNode('price', $p), Type::FLOAT, $p);
        $call = new FunctionCallNode('COUNT', [new ColumnReferenceNode('id', $p)], true, $p);

        $this->assertSame('CAST(price AS DOUBLE)', (new FqlPrinter())->print($cast));
        $this->assertSame('COUNT(DISTINCT id)', (new FqlPrinter())->print($call));
    }

    /**
     * @return array<string, array{AstNode}>
     */
    public static function unprintable(): array
    {
        $p = Position::synthetic();
        $col = static fn (string $name): ColumnReferenceNode => new ColumnReferenceNode($name, $p);
        $sum = new BinaryOpNode($col('a'), BinaryOperator::ADD, $col('b'), $p);
        $difference = new BinaryOpNode($col('b'), BinaryOperator::SUBTRACT, $col('c'), $p);
        return [
            '(a + b) * c' => [new BinaryOpNode($sum, BinaryOperator::MULTIPLY, $col('c'), $p)],
            'a - (b - c)' => [new BinaryOpNode($col('a'), BinaryOperator::SUBTRACT, $difference, $p)],
            'a * -5' => [new BinaryOpNode($col('a'), BinaryOperator::MULTIPLY, new LiteralNode(-5, Type::INTEGER, '', $p), $p)],
            'alias with backtick' => [new SelectFieldNode($col('a'), 'x`y', false, $p)],
            'column with backtick' => [$col('a`b')],
            'cast to TRUE' => [new CastExpressionNode($col('a'), Type::TRUE, $p)],
            'IN with arithmetic' => [new ConditionExpressionNode($col('a'), Operator::IN, [$sum], $p)],
            'IS without type' => [new ConditionExpressionNode($col('a'), Operator::IS, $col('b'), $p)],
            'function named like a keyword' => [new FunctionCallNode('LEFT', [$col('a')], false, $p)],
            'empty GROUP BY' => [new GroupByClauseNode([], $p)],
            'whole row' => [new WholeRowNode($p)],
            'condition as operand' => [new OrderByItemNode(
                new ConditionGroupNode([['logical' => LogicalOperator::AND, 'condition' => new ConditionExpressionNode($col('a'), Operator::EQUAL, $col('b'), $p)]], $p),
                Sort::ASC,
                $p
            )],
        ];
    }

    #[DataProvider('unprintable')]
    public function testTreesFqlCannotExpressAreRejected(AstNode $node): void
    {
        $this->expectException(UnprintableNodeException::class);
        (new FqlPrinter())->print($node);
    }

    public function testExceptionCarriesTheNode(): void
    {
        $p = Position::synthetic();
        $node = new SelectFieldNode(new ColumnReferenceNode('a', $p), 'x`y', false, $p);
        try {
            (new FqlPrinter())->print($node);
            $this->fail('Expected UnprintableNodeException');
        } catch (UnprintableNodeException $e) {
            $this->assertSame($node, $e->node);
            $this->assertStringContainsString('backtick', $e->getMessage());
        }
    }
}
