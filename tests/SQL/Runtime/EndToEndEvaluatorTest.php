<?php

namespace SQL\Runtime;

use FQL\Query\Provider as QueryProvider;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end verification of the runtime expression evaluator against real JSON data.
 *
 * Covers the use cases that motivated the evaluator introduction in 3.0.0:
 *  - Nested function calls (`UPPER(LOWER(x))`, `LENGTH(CONCAT(a, b))`)
 *  - Arithmetic expressions in function arguments (`ROUND(5 * price, 2)`)
 *  - Function calls in WHERE / HAVING / GROUP BY / ORDER BY clauses
 *  - Aggregates over expressions (`SUM(price * 2)`, `AVG(price - 10)`)
 */
class EndToEndEvaluatorTest extends TestCase
{
    private string $productsPath;

    protected function setUp(): void
    {
        $this->productsPath = (string) realpath(__DIR__ . '/../../../examples/data/products.json');
    }

    /**
     * @param string $sql
     * @return array<int, array<string, mixed>>
     */
    private function runSql(string $sql): array
    {
        $resolved = sprintf($sql, $this->productsPath);
        return iterator_to_array(QueryProvider::fql($resolved)->execute()->fetchAll());
    }

    // ─── Nested function calls ──────────────────────────────────────────

    public function testNestedUpperLower(): void
    {
        $rows = $this->runSql('SELECT id, UPPER(LOWER(name)) AS up FROM json(%s).data.products LIMIT 2');
        $this->assertCount(2, $rows);
        $this->assertSame('PRODUCT A', $rows[0]['up']);
        $this->assertSame('PRODUCT B', $rows[1]['up']);
    }

    public function testNestedLengthOfConcat(): void
    {
        $rows = $this->runSql(
            'SELECT LENGTH(CONCAT(name, " - ", brand.name)) AS total '
            . 'FROM json(%s).data.products LIMIT 1'
        );
        $this->assertCount(1, $rows);
        // "Product A - Brand A" = 19 chars
        $this->assertSame(19, $rows[0]['total']);
    }

    // ─── Math inside function arguments ─────────────────────────────────

    public function testRoundOfBinaryOp(): void
    {
        $rows = $this->runSql(
            'SELECT id, ROUND(5 * price, 2) AS fivefold FROM json(%s).data.products LIMIT 3'
        );
        $this->assertCount(3, $rows);
        $this->assertSame(500.0, $rows[0]['fivefold']);
        $this->assertSame(1000.0, $rows[1]['fivefold']);
        $this->assertSame(1500.0, $rows[2]['fivefold']);
    }

    public function testArithmeticInSelect(): void
    {
        $rows = $this->runSql(
            'SELECT id, price + 10 AS p, price - 5 AS p2, price * 2 AS double FROM json(%s).data.products LIMIT 2'
        );
        $this->assertSame(110, $rows[0]['p']);
        $this->assertSame(95, $rows[0]['p2']);
        $this->assertSame(200, $rows[0]['double']);
    }

    // ─── WHERE with function calls ──────────────────────────────────────

    public function testWhereLowerLike(): void
    {
        $rows = $this->runSql(
            'SELECT id, name FROM json(%s).data.products WHERE LOWER(name) LIKE "product a%%"'
        );
        $this->assertCount(1, $rows);
        $this->assertSame('Product A', $rows[0]['name']);
    }

    public function testWhereArithmeticCondition(): void
    {
        $rows = $this->runSql(
            'SELECT id, price FROM json(%s).data.products WHERE price * 2 > 400 ORDER BY id ASC'
        );
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertGreaterThan(200, $row['price']);
        }
    }

    public function testWhereNestedFunctionWithLength(): void
    {
        $rows = $this->runSql(
            'SELECT id, name FROM json(%s).data.products WHERE LENGTH(name) = 9 ORDER BY id ASC'
        );
        $this->assertNotEmpty($rows);
    }

    // ─── HAVING with aggregate expression ────────────────────────────────

    public function testHavingOnAggregateAlias(): void
    {
        $rows = $this->runSql(
            'SELECT brand.name AS brand, COUNT(id) AS cnt '
            . 'FROM json(%s).data.products GROUP BY brand.name HAVING cnt > 1'
        );
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertGreaterThan(1, $row['cnt']);
        }
    }

    // ─── GROUP BY / ORDER BY with function calls ─────────────────────────

    public function testGroupByLowerOnField(): void
    {
        $rows = $this->runSql(
            'SELECT COUNT(id) AS cnt FROM json(%s).data.products GROUP BY LOWER(brand.code)'
        );
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            // Auto-promoted fields must NOT leak into the result (runtime evaluator works
            // without the legacy ClauseRewriter auto-promote scheme).
            foreach (array_keys($row) as $key) {
                $this->assertStringStartsNotWith('__fql_auto_', $key);
            }
        }
    }

    public function testOrderByLengthOfName(): void
    {
        $rows = $this->runSql(
            'SELECT id, name FROM json(%s).data.products ORDER BY LENGTH(name) DESC LIMIT 3'
        );
        $this->assertCount(3, $rows);
        // No auto-promoted fields should appear in the output.
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('__fql_auto_1', $row);
        }
    }

    public function testOrderByArithmeticExpression(): void
    {
        $rows = $this->runSql(
            'SELECT id, price FROM json(%s).data.products ORDER BY price * 0.9 DESC LIMIT 3'
        );
        $this->assertCount(3, $rows);
        // Rows should be in descending price order (since * 0.9 is monotonic).
        $prices = array_column($rows, 'price');
        $sorted = $prices;
        rsort($sorted);
        $this->assertSame($sorted, $prices);
    }

    // ─── Aggregates over expressions ─────────────────────────────────────

    public function testSumOverExpression(): void
    {
        $rows = $this->runSql(
            'SELECT SUM(price * 2) AS total FROM json(%s).data.products'
        );
        $this->assertCount(1, $rows);
        // Sum of prices × 2 across all products.
        $this->assertSame(2800, $rows[0]['total']);
    }

    public function testAvgOverExpression(): void
    {
        $rows = $this->runSql(
            'SELECT AVG(price) AS avg FROM json(%s).data.products'
        );
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(280, $rows[0]['avg'], 0.1);
    }

    public function testMinMaxOverExpression(): void
    {
        $rows = $this->runSql(
            'SELECT MIN(price + 10) AS lo, MAX(price - 10) AS hi FROM json(%s).data.products'
        );
        $this->assertCount(1, $rows);
        $this->assertSame(110, $rows[0]['lo']);
        $this->assertSame(390, $rows[0]['hi']);
    }

    public function testCountStarStillWorks(): void
    {
        $rows = $this->runSql('SELECT COUNT(*) AS total FROM json(%s).data.products');
        $this->assertSame(5, $rows[0]['total']);
    }

    public function testGroupConcatOverExpression(): void
    {
        $rows = $this->runSql(
            'SELECT GROUP_CONCAT(UPPER(name), "|") AS list FROM json(%s).data.products'
        );
        $this->assertCount(1, $rows);
        $this->assertIsString($rows[0]['list']);
        $this->assertStringContainsString('PRODUCT A', $rows[0]['list']);
        $this->assertStringContainsString('|', $rows[0]['list']);
    }

    // ─── Combined complex scenario ──────────────────────────────────────

    public function testCombinedNestedMathAggregateWhereOrderBy(): void
    {
        $rows = $this->runSql(
            'SELECT brand.name AS brand, SUM(price * 2) AS boost '
            . 'FROM json(%s).data.products '
            . 'WHERE LOWER(name) LIKE "product%%" '
            . 'GROUP BY brand.name '
            . 'HAVING boost > 100 '
            . 'ORDER BY boost DESC'
        );
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertGreaterThan(100, $row['boost']);
            $this->assertArrayHasKey('brand', $row);
        }
    }

    /**
     * Regression: `IF(<backtick-chained-path> IS ARRAY, ...)` used to evaluate
     * to NULL on every row because the QueryBuildingVisitor stringified the
     * IF expression via ExpressionCompiler and Operator::render() chopped the
     * outer pair from `` `brand`.`name` ``, producing an unparseable lexeme.
     * The re-parse failed silently and the IF collapsed to a stray
     * ColumnReferenceNode at SELECT-time. Lock the fix end-to-end.
     */
    public function testIfWithBacktickChainedPathReturnsBranchValue(): void
    {
        $rows = $this->runSql(
            'SELECT IF(`brand`.`name` IS ARRAY, "fallback", `brand`.`name`) AS brand '
            . 'FROM json(%s).data.products LIMIT 3'
        );
        $this->assertCount(3, $rows);
        // brand.name is a scalar string for every product fixture row, so
        // IS ARRAY is false → IF returns the else branch (the brand name).
        // Pre-fix, every brand was null because the IF re-parse blew up.
        foreach ($rows as $row) {
            $this->assertNotNull($row['brand']);
            $this->assertIsString($row['brand']);
            $this->assertNotSame('fallback', $row['brand']);
        }
    }
}
