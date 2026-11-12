<?php

namespace SQL\Runtime;

use FQL\Query\Provider as QueryProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for column path access reused across rows: the same path is
 * prepared once per evaluator and must keep the exact lookup semantics for every row,
 * across repeated executions and between independent queries.
 */
class ColumnPathAccessTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/' . uniqid('fql-paths-') . '.json';
        file_put_contents($this->path, (string) json_encode([
            'rows' => [
                [
                    'id' => 1,
                    'price' => 10,
                    'note' => 'first',
                    'key.with.dot' => 'dotted-1',
                    'meta' => ['tags' => [['name' => 'a'], ['name' => 'b']], 'score' => 5],
                ],
                [
                    'id' => 2,
                    'price' => 20,
                    'note' => null,
                    'key.with.dot' => 'dotted-2',
                    'meta' => ['tags' => [['name' => 'c']], 'score' => null],
                ],
                [
                    'id' => 3,
                    'price' => 30,
                    'key.with.dot' => 'dotted-3',
                    'meta' => ['tags' => [], 'score' => 7],
                ],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runSql(string $sql): array
    {
        return iterator_to_array(QueryProvider::fql(sprintf($sql, $this->path))->execute()->fetchAll());
    }

    public function testPathsResolvePerRow(): void
    {
        $rows = $this->runSql(
            'SELECT id, note, `key.with.dot`, meta.score, meta.tags[].name AS tags, meta.tags.`0`.name AS firstTag '
            . 'FROM json(%s).rows'
        );

        $this->assertSame([1, 2, 3], array_column($rows, 'id'));
        $this->assertSame(['first', null, null], array_column($rows, 'note'));
        $this->assertSame(['dotted-1', 'dotted-2', 'dotted-3'], array_column($rows, 'key.with.dot'));
        $this->assertSame([5, null, 7], array_column($rows, 'meta.score'));
        $this->assertSame([['a', 'b'], ['c'], [null]], array_column($rows, 'tags'));
        $this->assertSame(['a', 'c', null], array_column($rows, 'firstTag'));
    }

    public function testWildcardPathExpandsPerRow(): void
    {
        $rows = $this->runSql('SELECT id, meta.* FROM json(%s).rows');

        $this->assertSame([5, null, 7], array_column($rows, 'score'));
        $this->assertSame([[['name' => 'a'], ['name' => 'b']], [['name' => 'c']], []], array_column($rows, 'tags'));
    }

    public function testFallbackFromRowToProjection(): void
    {
        $rows = $this->runSql('SELECT id, price * 2 AS doubled, doubled + 1 AS nextValue FROM json(%s).rows');

        $this->assertEquals([21, 41, 61], array_column($rows, 'nextValue'));
    }

    public function testWhereAndOrderByShareTheSamePaths(): void
    {
        $rows = $this->runSql(
            'SELECT id, price FROM json(%s).rows WHERE price > 10 AND meta.score IS NOT NULL ORDER BY price DESC'
        );

        $this->assertSame([3], array_column($rows, 'id'));
    }

    public function testRepeatedExecutionAndIsolatedQueries(): void
    {
        $byPrice = QueryProvider::fql(sprintf('SELECT id, price FROM json(%s).rows ORDER BY price DESC', $this->path));
        $byDotted = QueryProvider::fql(
            sprintf('SELECT `key.with.dot` AS id FROM json(%s).rows WHERE id >= 2', $this->path)
        );

        foreach ([1, 2] as $run) {
            $this->assertSame(
                [3, 2, 1],
                array_column(iterator_to_array($byPrice->execute()->fetchAll()), 'id'),
                "run $run"
            );
            $this->assertSame(
                ['dotted-2', 'dotted-3'],
                array_column(iterator_to_array($byDotted->execute()->fetchAll()), 'id'),
                "run $run"
            );
        }
    }
}
