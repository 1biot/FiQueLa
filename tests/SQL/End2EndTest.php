<?php

namespace SQL;

use FQL\Query\FileQuery;
use FQL\Sql\Provider as SqlProvider;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end scenarios against real fixture files. Designed to exercise the full
 * Token → AST → Builder → Query pipeline for combinations that unit tests do not
 * hit individually (multi-column ORDER BY, LIMIT+OFFSET variants, INTO with
 * basePath, MATCH/AGAINST search, CASE fallthrough, etc.).
 */
class End2EndTest extends TestCase
{
    private string $productsJson;
    private string $usersJson;

    protected function setUp(): void
    {
        $this->productsJson = (string) realpath(__DIR__ . '/../../examples/data/products.json');
        $this->usersJson = (string) realpath(__DIR__ . '/../../examples/data/users.json');
    }

    public function testOrderByAscWithMultipleFields(): void
    {
        $sql = sprintf(
            'SELECT id, name, price FROM json(%s).data.products ORDER BY price ASC, name ASC',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $prices = array_column($rows, 'price');
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices);
    }

    public function testOrderByDescRespectsDirection(): void
    {
        $sql = sprintf(
            'SELECT price FROM json(%s).data.products ORDER BY price DESC',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $prices = array_column($rows, 'price');
        $sorted = $prices;
        rsort($sorted);
        $this->assertSame($sorted, $prices);
    }

    public function testLimitOffsetSeparateKeyword(): void
    {
        $sql = sprintf(
            'SELECT id FROM json(%s).data.products ORDER BY id ASC LIMIT 2 OFFSET 1',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $this->assertCount(2, $rows);
    }

    public function testLimitCommaShorthand(): void
    {
        // FQL `LIMIT n, m` — limit=n with offset=m (legacy behaviour).
        $sql = sprintf(
            'SELECT id FROM json(%s).data.products ORDER BY id ASC LIMIT 2, 1',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $this->assertCount(2, $rows);
        $this->assertNotSame(1, $rows[0]['id']);
    }

    public function testLimitWhitespaceOffsetShorthand(): void
    {
        // Legacy MySQL-style "LIMIT n m" — offset separated by whitespace only.
        $sql = sprintf(
            'SELECT id FROM json(%s).data.products ORDER BY id ASC LIMIT 2 1',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $this->assertCount(2, $rows);
        $this->assertNotSame(1, $rows[0]['id']);
    }

    public function testGroupByWithHavingAndSort(): void
    {
        $sql = sprintf(
            'SELECT brand.name AS brand, COUNT(id) AS total FROM json(%s).data.products '
            . 'GROUP BY brand.name HAVING total > 1 ORDER BY total DESC',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertGreaterThan(1, $row['total']);
        }
    }

    public function testCaseWithMultipleBranchesAndFallthroughElse(): void
    {
        $sql = sprintf(
            'SELECT id, CASE WHEN price > 300 THEN "premium" '
            . 'WHEN price > 100 THEN "mid" ELSE "budget" END AS tier '
            . 'FROM json(%s).data.products',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        foreach ($rows as $row) {
            $this->assertContains($row['tier'], ['premium', 'mid', 'budget']);
        }
    }

    public function testMatchAgainstReturnsScore(): void
    {
        $sql = sprintf(
            'SELECT id, MATCH(name) AGAINST("Product A IN NATURAL MODE") AS score '
            . 'FROM json(%s).data.products',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertArrayHasKey('score', $row);
        }
    }

    public function testIntoWithBasePathResolvesFileTarget(): void
    {
        // Use a basePath that contains both the source file and the INTO target so the
        // path validator is satisfied. We stage a symlink to the products.json fixture
        // inside the temp basePath.
        $basePath = sys_get_temp_dir() . '/fiquela-e2e-' . uniqid();
        mkdir($basePath, 0775, true);
        $stagedSource = $basePath . '/products.json';
        copy($this->productsJson, $stagedSource);

        try {
            $sql = 'SELECT id INTO csv(output.csv) FROM json(products.json).data.products';
            $query = SqlProvider::compile($sql, $basePath)->toQuery();
            $this->assertTrue($query->hasInto());
            $into = $query->getInto();
            $this->assertInstanceOf(FileQuery::class, $into);
            $this->assertSame(
                realpath($basePath) . '/output.csv',
                $into->file
            );
        } finally {
            if (is_file($stagedSource)) {
                unlink($stagedSource);
            }
            rmdir($basePath);
        }
    }

    public function testUnionWithDedup(): void
    {
        $sql = sprintf(
            'SELECT id, name FROM json(%s).data.products WHERE id = 1 '
            . 'UNION '
            . 'SELECT id, name FROM json(%s).data.products WHERE id = 1',
            $this->productsJson,
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $ids = array_column($rows, 'id');
        // UNION (without ALL) should dedup duplicates.
        $this->assertSame(array_values(array_unique($ids)), array_values($ids));
    }

    public function testWhereWithInListAndRegexp(): void
    {
        $sql = sprintf(
            'SELECT id, name FROM json(%s).data.products WHERE id IN (1, 2, 3) AND name REGEXP "Product"',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertContains($row['id'], [1, 2, 3]);
        }
    }

    public function testWhereWithNestedParenthesizedGroups(): void
    {
        $sql = sprintf(
            'SELECT id FROM json(%s).data.products WHERE (id = 1 OR id = 2) AND price > 0',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $ids = array_column($rows, 'id');
        sort($ids);
        $this->assertEquals([1, 2], array_values(array_intersect([1, 2], $ids)));
    }

    public function testExcludeFieldsCanAppearAfterSelect(): void
    {
        $sql = sprintf(
            'SELECT * EXCLUDE description FROM json(%s).data.products LIMIT 1',
            $this->productsJson
        );
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $this->assertArrayNotHasKey('description', $rows[0]);
    }

    public function testCommentsInSqlAreIgnoredByParser(): void
    {
        $sql = "-- this is a comment\n"
            . "SELECT id /* block */ FROM json({$this->productsJson}).data.products LIMIT 1\n"
            . "# trailing comment";
        $rows = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        );
        $this->assertCount(1, $rows);
    }

    public function testFieldAliasPropagatesToResultKeys(): void
    {
        $sql = sprintf(
            'SELECT id AS productId, name AS productName FROM json(%s).data.products LIMIT 1',
            $this->productsJson
        );
        $row = iterator_to_array(
            SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()
        )[0];
        $this->assertArrayHasKey('productId', $row);
        $this->assertArrayHasKey('productName', $row);
    }

    public function testCompilerCachesTokensAndAstAcrossCalls(): void
    {
        $sql = sprintf('SELECT id FROM json(%s).data.products LIMIT 1', $this->productsJson);
        $compiler = SqlProvider::compile($sql);

        $tokens1 = $compiler->toTokens();
        $tokens2 = $compiler->toTokens();
        $this->assertSame($tokens1, $tokens2);

        $ast1 = $compiler->toAst();
        $ast2 = $compiler->toAst();
        $this->assertSame($ast1, $ast2);

        $stream = $compiler->toTokenStream(includeTrivia: false);
        $this->assertNotNull($stream);

        $this->assertNull($compiler->getBasePath());
    }
}
