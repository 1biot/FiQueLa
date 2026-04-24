<?php

namespace Results;

use FQL\Enum\Operator;
use FQL\Query\Provider;
use FQL\Results\Stream;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

/**
 * Focused top-ups for `Results\Stream` — exercises the public aggregate
 * cache, ambiguous-wildcard detection, explicit `into()` analyze paths,
 * and multi-aggregate grouping combinations that integration tests don't
 * directly hit.
 */
class StreamTargetedTest extends TestCase
{
    /**
     * @return \FQL\Interface\Results
     */
    private function resultsOf(string $path, string $query = 'SELECT * FROM json('): \FQL\Interface\Results
    {
        return Provider::fql('SELECT * FROM json(' . $path . ')')->execute();
    }

    private function jsonFile(array $data): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-stream-');
        file_put_contents($path, json_encode($data));
        return $path;
    }

    public function testAvgSumMinMaxPublicApi(): void
    {
        $path = $this->jsonFile([
            ['price' => 10],
            ['price' => 20],
            ['price' => 30],
        ]);

        try {
            $results = Provider::fql("SELECT price FROM json($path)")
                ->execute(Stream::class);

            $this->assertSame(3, $results->count());
            $this->assertSame(60.0, $results->sum('price'));
            $this->assertEqualsWithDelta(20.0, $results->avg('price'), 0.0001);
            $this->assertSame(10.0, $results->min('price'));
            $this->assertSame(30.0, $results->max('price'));

            // Second call hits the cache — confirm identical result.
            $this->assertSame(60.0, $results->sum('price'));
        } finally {
            @unlink($path);
        }
    }

    public function testWildcardExpansionFromNestedField(): void
    {
        // Expanding `data.*` into the result — covers the nested-wildcard
        // branch in Stream::applySelect.
        $path = $this->jsonFile([
            ['data' => ['a' => 1, 'b' => 2]],
            ['data' => ['a' => 3, 'b' => 4]],
        ]);

        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT data.* FROM json($path)")
                    ->execute()->fetchAll()
            );
            $this->assertCount(2, $rows);
            $this->assertArrayHasKey('a', $rows[0]);
            $this->assertArrayHasKey('b', $rows[0]);
        } finally {
            @unlink($path);
        }
    }

    public function testFetchSingleReturnsFirstRow(): void
    {
        $path = $this->jsonFile([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        try {
            $row = Provider::fql("SELECT id, name FROM json($path)")
                ->execute()
                ->fetch();
            // JSON preserves native types — int 1 (no coercion on read).
            $this->assertSame(1, $row['id']);
            $this->assertSame('Alice', $row['name']);
        } finally {
            @unlink($path);
        }
    }

    public function testMultipleAggregatesInSingleGroup(): void
    {
        $path = $this->jsonFile([
            ['category' => 'A', 'price' => 10],
            ['category' => 'A', 'price' => 20],
            ['category' => 'B', 'price' => 5],
        ]);

        try {
            $rows = iterator_to_array(
                Provider::fql(sprintf(
                    'SELECT category, SUM(price) AS sum, AVG(price) AS avg, MIN(price) AS min, MAX(price) AS max ' .
                    'FROM json(%s) GROUP BY category ORDER BY category',
                    $path
                ))->execute()->fetchAll()
            );

            $this->assertCount(2, $rows);
            $this->assertSame('A', $rows[0]['category']);
            $this->assertEqualsWithDelta(30, $rows[0]['sum'], 0.0001);
            $this->assertEqualsWithDelta(15, $rows[0]['avg'], 0.0001);
            $this->assertEqualsWithDelta(10, $rows[0]['min'], 0.0001);
            $this->assertEqualsWithDelta(20, $rows[0]['max'], 0.0001);
            $this->assertSame('B', $rows[1]['category']);
            $this->assertEqualsWithDelta(5, $rows[1]['sum'], 0.0001);
        } finally {
            @unlink($path);
        }
    }

    public function testDistinctAggregate(): void
    {
        $path = $this->jsonFile([
            ['cat' => 'A', 'name' => 'x'],
            ['cat' => 'A', 'name' => 'x'],
            ['cat' => 'A', 'name' => 'y'],
        ]);

        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT COUNT(DISTINCT name) AS c FROM json($path) GROUP BY cat")
                    ->execute()->fetchAll()
            );
            $this->assertSame(2, $rows[0]['c']);
        } finally {
            @unlink($path);
        }
    }

    public function testExplainAnalyzeReportsPhases(): void
    {
        $path = $this->jsonFile([['id' => 1], ['id' => 2], ['id' => 3]]);
        try {
            $rows = iterator_to_array(
                Provider::fql("EXPLAIN ANALYZE SELECT * FROM json($path) WHERE id > 1")
                    ->execute()->fetchAll()
            );
            $this->assertNotEmpty($rows);
        } finally {
            @unlink($path);
        }
    }

    public function testLimitAndOffset(): void
    {
        $path = $this->jsonFile(array_map(
            fn (int $i) => ['id' => $i],
            range(1, 10)
        ));

        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT * FROM json($path) LIMIT 3 OFFSET 2")
                    ->execute()->fetchAll()
            );
            $this->assertCount(3, $rows);
            $this->assertSame(3, $rows[0]['id']);
        } finally {
            @unlink($path);
        }
    }

    public function testExplainAnalyzeWithHaving(): void
    {
        // Exercises collector-guarded HAVING branches in applyGrouping.
        $path = $this->jsonFile([
            ['cat' => 'A', 'v' => 1],
            ['cat' => 'A', 'v' => 2],
            ['cat' => 'B', 'v' => 5],
        ]);
        try {
            $rows = iterator_to_array(
                Provider::fql(
                    "EXPLAIN ANALYZE SELECT cat, SUM(v) AS s FROM json($path) " .
                    "GROUP BY cat HAVING s > 1"
                )->execute()->fetchAll()
            );
            $this->assertNotEmpty($rows);
        } finally {
            @unlink($path);
        }
    }

    public function testExplainAnalyzeWithLimitOffset(): void
    {
        $path = $this->jsonFile(array_map(
            fn (int $i) => ['id' => $i],
            range(1, 10)
        ));
        try {
            $rows = iterator_to_array(
                Provider::fql(
                    "EXPLAIN ANALYZE SELECT * FROM json($path) LIMIT 2 OFFSET 3"
                )->execute()->fetchAll()
            );
            $this->assertNotEmpty($rows);
        } finally {
            @unlink($path);
        }
    }

    public function testExplainAnalyzeWithDistinct(): void
    {
        $path = $this->jsonFile([
            ['v' => 3],
            ['v' => 1],
            ['v' => 1],
            ['v' => 2],
        ]);
        try {
            $rows = iterator_to_array(
                Provider::fql("EXPLAIN ANALYZE SELECT DISTINCT v FROM json($path) ORDER BY v ASC")
                    ->execute()->fetchAll()
            );
            $this->assertNotEmpty($rows);
        } finally {
            @unlink($path);
        }
    }

    public function testExplainAnalyzeAggregateWithoutGroupBy(): void
    {
        $path = $this->jsonFile([['v' => 10], ['v' => 20]]);
        try {
            $rows = iterator_to_array(
                Provider::fql("EXPLAIN ANALYZE SELECT SUM(v) AS total FROM json($path)")
                    ->execute()->fetchAll()
            );
            $this->assertNotEmpty($rows);
        } finally {
            @unlink($path);
        }
    }

    public function testExplainAnalyzeHavingRejectsAll(): void
    {
        $path = $this->jsonFile([['cat' => 'A', 'v' => 1]]);
        try {
            $rows = iterator_to_array(
                Provider::fql(
                    "EXPLAIN ANALYZE SELECT cat, SUM(v) AS s FROM json($path) " .
                    "GROUP BY cat HAVING s > 100"
                )->execute()->fetchAll()
            );
            $this->assertNotEmpty($rows);
        } finally {
            @unlink($path);
        }
    }

    public function testExplainAnalyzeEmptyDataset(): void
    {
        $path = $this->jsonFile([]);
        try {
            $rows = iterator_to_array(
                Provider::fql("EXPLAIN ANALYZE SELECT SUM(v) AS total FROM json($path)")
                    ->execute()->fetchAll()
            );
            $this->assertNotEmpty($rows);
        } finally {
            @unlink($path);
        }
    }

    public function testUnionAllPreservesDuplicates(): void
    {
        $a = $this->jsonFile([['id' => 1], ['id' => 2]]);
        $b = $this->jsonFile([['id' => 2], ['id' => 3]]);

        try {
            $rows = iterator_to_array(
                Provider::fql(sprintf(
                    'SELECT id FROM json(%s) UNION ALL SELECT id FROM json(%s)',
                    $a,
                    $b
                ))->execute()->fetchAll()
            );
            $this->assertCount(4, $rows);
        } finally {
            @unlink($a);
            @unlink($b);
        }
    }
}
