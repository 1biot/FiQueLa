<?php

namespace SQL\Printer;

use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Compiler;
use FQL\Sql\Formatter\SqlFormatter;
use FQL\Sql\Printer\FqlPrinter;
use FQL\Sql\Printer\SourcePrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AstShape.php';

/**
 * Round-trip invariants of the printers over a broad corpus of valid FQL.
 */
class SourcePrinterRoundTripTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function corpus(): array
    {
        return [
            'plain select' => ['SELECT a FROM json(a.json)'],
            'from star' => ["SELECT * FROM *"],
            'lowercase keywords' => ["select id, name from csv(./data/products.csv)"],
            'mixed case keywords' => ["SeLeCt id FrOm json(a.json) WhErE id > 1"],
            'multi-line layout' => ["SELECT\n    id,\n    name AS `Název zboží`,\n    price * 1.21 AS priceVat\nFROM xml(./feed.xml).SHOP.SHOPITEM\nWHERE\n    price > 100\n    AND name LIKE \"%kolo%\"\nORDER BY price DESC\nLIMIT 10"],
            'comments of every kind' => ["-- leading comment\nSELECT a /* inline */ , b -- trailing\nFROM json(a.json) # hash comment\nWHERE a = 1 /* before and */ AND b = 2\n-- final comment\n"],
            'surrounding whitespace' => ["  \n\tSELECT a FROM json(a.json)\t\n\n"],
            'CRLF line endings' => ["SELECT a,\r\n       b\r\nFROM json(a.json)\r\nWHERE a = 'x'\r\n"],
            'quoted identifiers' => ["SELECT `my col`, `a`.`b`, a.`b c`, `Kód`, `a.b`, `info`.date FROM json(a.json)"],
            'escaped paths and arrays' => ["SELECT `Název Zboží.cz` AS nazev, products.product[] AS items, LENGTH(`products`.`product`[]) AS itemCount FROM json(a.json)"],
            'attribute and dollar paths' => ["SELECT @attributes.id, \$meta.x, order-total FROM xml(a.xml).ROOT.ITEM"],
            'table star and inner join' => ["SELECT p.*, c.name FROM json(p.json) AS p INNER JOIN json(c.json) AS c ON p.cid = c.id"],
            'every join type' => ["SELECT * FROM json(a.json) AS a LEFT OUTER JOIN csv(b.csv, \"windows-1250\", \";\") AS b ON a.id == b.id RIGHT JOIN json(c.json) AS c ON c.id = a.id FULL JOIN json(d.json) AS d ON d.id = a.id JOIN json(e.json) AS e ON e.id = a.id"],
            'subquery in JOIN' => ["SELECT * FROM json(a.json) AS a LEFT JOIN (SELECT id FROM json(b.json)) AS b ON a.id = b.id"],
            'two CTEs' => ["WITH active AS (SELECT * FROM json(u.json) WHERE active = true), recent AS (SELECT * FROM active WHERE year >= 2020) SELECT name FROM recent ORDER BY name"],
            'multi-line CTE' => ["WITH\n  a AS (\n    SELECT x FROM json(a.json)\n  )\nSELECT * FROM a"],
            'union' => ["SELECT name FROM json(a.json) UNION SELECT name FROM json(b.json)"],
            'union chain' => ["SELECT name, price FROM xml(./f2.xml).SHOP.ITEM\nWHERE price > 200\nUNION ALL\nSELECT name, price FROM xml(./f3.xml).SHOP.ITEM\nUNION\nSELECT name, price FROM xml(./f4.xml).SHOP.ITEM ORDER BY price"],
            'case with else' => ["SELECT CASE WHEN price > 100 THEN \"expensive\" WHEN price > 50 THEN 'mid' ELSE \"cheap\" END AS tier FROM json(a.json)"],
            'case with nested group' => ["SELECT CASE WHEN a = 1 AND (b = 2 OR c = 3) THEN 1 END FROM json(a.json)"],
            'exclude list' => ["SELECT *, totalPrice EXCLUDE secret, `tajné pole` FROM json(a.json)"],
            'exclude without comma' => ["SELECT * EXCLUDE a FROM json(a.json)"],
            'exclude with comma' => ["SELECT a, EXCLUDE b FROM json(a.json)"],
            'exclude on its own line' => ["SELECT\n  brand.code AS brandCode,\n  GROUP_CONCAT(id, \"/\") AS products,\n  SUM(price) AS totalPrice,\n  COUNT(productId) AS productCount\nEXCLUDE productCount\nFROM jsonFile(./products.tmp).data.products\nHAVING productCount > 1"],
            'distinct' => ["SELECT DISTINCT brand FROM json(a.json)"],
            'functions' => ["SELECT COUNT(DISTINCT brand) AS brands, COUNT(*), NOW(), ROUND(price, 2), LOWER(name) FROM json(a.json)"],
            'if' => ["SELECT IF(price > 100, \"high\", \"low\") AS level FROM json(a.json)"],
            'cast' => ["SELECT CAST(price AS INT), cast(x as double), CAST(y AS NULL) FROM json(a.json)"],
            'match natural' => ["SELECT MATCH(title, description) AGAINST(\"kolo IN NATURAL MODE\") AS score FROM json(a.json)"],
            'match boolean' => ["SELECT MATCH(title) AGAINST(\"+kolo -auto\" IN BOOLEAN MODE) FROM json(a.json)"],
            'collect object' => ["SELECT COLLECT_OBJECT(id, name AS n ORDER BY name DESC) AS items FROM json(a.json) GROUP BY brand"],
            'arithmetic' => ["SELECT price * 2 + 1, a - b - c, a * b / c % d, x + -5 FROM json(a.json)"],
            'in and between' => ["SELECT a FROM json(a.json) WHERE a IN (1, 2, \"tři\") AND b NOT IN ('x') AND c BETWEEN 1 AND 10 AND d NOT BETWEEN -1 AND 1.5"],
            'is types' => ["SELECT a FROM json(a.json) WHERE a IS NULL OR b IS NOT NULL XOR c IS NUMBER AND d IS TRUE AND e IS NOT false"],
            'like and regexp' => ["SELECT a FROM json(a.json) WHERE name LIKE \"Č%\" AND name NOT LIKE '%x' AND code REGEXP \"^[A-Z]+$\" AND code NOT REGEXP 'x'"],
            'nested groups' => ["SELECT a FROM json(a.json) WHERE a = 1 AND (b = 2 OR (c = 3 AND d != 4)) OR e <> 5"],
            'parenthesised where' => ["SELECT a FROM json(a.json) WHERE (a = 1)"],
            'comparison operators' => ["SELECT a FROM json(a.json) WHERE a == 1 AND b !== 2 AND c >= 3 AND d <= 4 AND e < 5 AND f > 6"],
            'column on the right' => ["SELECT a FROM json(a.json) WHERE a = `b` AND c = d.e"],
            'functions in where' => ["SELECT a FROM json(a.json) WHERE LOWER(name) = \"praha\" AND LENGTH(name) > 3"],
            'group by having' => ["SELECT brand, COUNT(*) AS cnt FROM json(a.json) GROUP BY brand, category HAVING cnt > 1 AND cnt < 100"],
            'order by' => ["SELECT a FROM json(a.json) ORDER BY a, b DESC, c ASC"],
            'limit' => ["SELECT a FROM json(a.json) LIMIT 5"],
            'limit shorthand' => ["SELECT a FROM json(a.json) LIMIT 5, 10"],
            'limit offset' => ["SELECT a FROM json(a.json) LIMIT 10 OFFSET 5"],
            'offset limit' => ["SELECT a FROM json(a.json) OFFSET 5 LIMIT 10"],
            'offset only' => ["SELECT a FROM json(a.json) OFFSET 5"],
            'limit split by order by' => ["SELECT a FROM json(a.json) LIMIT 10 ORDER BY a OFFSET 5"],
            'repeated where' => ["SELECT a FROM json(a.json) WHERE a = 1 WHERE b = 2"],
            'into' => ["SELECT name, price FROM csv(./products.csv).* INTO csv(./exports/products.csv)"],
            'into with params' => ["SELECT * FROM csv(a.csv, delimiter: \";\", enclosure: \"'\") INTO xml(./out.xml).ROOT.ROW"],
            'log pattern' => ["SELECT * FROM log(./access.log, pattern: \"%h %m %U\")"],
            'describe' => ["DESCRIBE json(./products.json).data.products"],
            'describe lowercase' => ["describe csv(./products.csv).*"],
            'explain' => ["EXPLAIN SELECT a FROM json(a.json)"],
            'explain analyze' => ["EXPLAIN ANALYZE SELECT a FROM json(a.json) WHERE a > 1"],
            'czech text' => ["SELECT \"Příliš žluťoučký kůň\" AS `věta`, 'úpěl ďábelské ódy' FROM json(a.json) WHERE `město` = \"Brno\" -- komentář česky\n"],
            'soft keyword column' => ["SELECT a FROM json(a.json) ORDER BY desc"],
            'keyword aliases' => ["SELECT a AS `select`, `from` FROM json(a.json)"],
            'literals' => ["SELECT TRUE, FALSE, NULL, true, null, 1.50, -3, .5 FROM json(a.json)"],
        ];
    }

    #[DataProvider('corpus')]
    public function testUnmodifiedAstPrintsTheOriginalText(string $sql): void
    {
        $printer = new SourcePrinter($sql);

        $this->assertSame($sql, $printer->print($printer->original()));
    }

    #[DataProvider('corpus')]
    public function testCopiedRootReusesEveryClause(string $sql): void
    {
        $printer = new SourcePrinter($sql);
        $copy = $printer->original()->withExplain($printer->original()->explain);

        $this->assertSame($sql, $printer->print($copy));
    }

    /**
     * Every node replaced by an equal new object: the printer has to splice each one
     * against its original counterpart, which must reproduce the text (LIMIT shorthands
     * and soft-keyword columns are normalised) and always parse back to the same tree.
     */
    #[DataProvider('corpus')]
    public function testDeepCopyIsSplicedBackToTheSameQuery(string $sql): void
    {
        $printer = new SourcePrinter($sql);
        $copy = AstShape::deepCopy($printer->original());

        $printed = $printer->print($copy);

        $this->assertEquals(AstShape::of($printer->original()), AstShape::of((new Compiler($printed))->toAst()));
        $normalised = [
            'SELECT a FROM json(a.json) LIMIT 5, 10' => 'SELECT a FROM json(a.json) LIMIT 5 OFFSET 10',
            'SELECT a FROM json(a.json) OFFSET 5 LIMIT 10' => 'SELECT a FROM json(a.json) LIMIT 10 OFFSET 5',
            'SELECT a FROM json(a.json) LIMIT 10 ORDER BY a OFFSET 5' => 'SELECT a FROM json(a.json) ORDER BY a LIMIT 10 OFFSET 5',
            'SELECT a FROM json(a.json) ORDER BY desc' => 'SELECT a FROM json(a.json) ORDER BY `desc`',
        ];
        $this->assertSame($normalised[$sql] ?? $sql, $printed);
    }

    /**
     * Random list edits anywhere in the tree (fixed seed): the printed text always parses
     * back to the edited tree.
     */
    #[DataProvider('corpus')]
    public function testRandomEditsParseBackToTheEditedTree(string $sql): void
    {
        mt_srand(crc32($sql));
        $printer = new SourcePrinter($sql);
        for ($run = 0; $run < 25; $run++) {
            $edited = RandomEdit::of($printer->original());
            $this->assertInstanceOf(SelectStatementNode::class, $edited);

            $printed = $printer->print($edited);

            $this->assertEquals(
                AstShape::of($edited),
                AstShape::of((new Compiler($printed))->toAst()),
                $printed
            );
        }
    }

    #[DataProvider('corpus')]
    public function testFreshPrintParsesBackToTheSameTree(string $sql): void
    {
        $ast = (new Compiler($sql))->toAst();

        $printed = (new FqlPrinter())->print($ast);

        $this->assertStringNotContainsString("\n", $printed);
        $this->assertEquals(AstShape::of($ast), AstShape::of((new Compiler($printed))->toAst()), $printed);
    }

    #[DataProvider('corpus')]
    public function testFormatterOutputParsesBackToTheSameTree(string $sql): void
    {
        $ast = (new Compiler($sql))->toAst();

        $formatted = (new SqlFormatter())->formatStatement($ast);

        $this->assertEquals(AstShape::of($ast), AstShape::of((new Compiler($formatted))->toAst()), $formatted);
    }

    public function testPrinterAcceptsAnAlreadyParsedAst(): void
    {
        $sql = "SELECT a -- x\nFROM json(a.json)";
        $ast = (new Compiler($sql))->toAst();

        $printer = new SourcePrinter($sql, $ast);

        $this->assertSame($ast, $printer->original());
        $this->assertSame($sql, $printer->sql());
        $this->assertSame($sql, $printer->print($ast));
    }

    public function testAstWithoutSpanIsRejected(): void
    {
        $ast = AstShape::deepCopy((new Compiler('SELECT a FROM json(a.json)'))->toAst());

        $this->expectException(\InvalidArgumentException::class);
        new SourcePrinter('SELECT a FROM json(a.json)', $ast);
    }
}
