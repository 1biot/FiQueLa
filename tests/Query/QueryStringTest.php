<?php

namespace Query;

use FQL\Enum\Operator;
use FQL\Query\FileQuery;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class QueryStringTest extends TestCase
{
    public function testQueryStringIncludesClauses(): void
    {
        $left = Json::string(json_encode([
            ['id' => 1, 'name' => 'A'],
        ]));
        $right = Json::string(json_encode([
            ['id' => 1, 'code' => 'X'],
        ]));

        $joinQuery = $right->query()->select('id, code');

        $query = $left->query()
            ->select('id')->as('userId')
            ->select('name')
            ->exclude('name')
            ->distinct()
            ->leftJoin($joinQuery, 'r')
                ->on('id', Operator::EQUAL, 'id')
            ->where('id', Operator::GREATER_THAN, 0)
            ->orderBy('userId')->desc()
            ->limit(10, 5);

        $sql = (string) $query;

        $this->assertStringContainsString('SELECT DISTINCT', $sql);
        $this->assertStringContainsString('EXCLUDE', $sql);
        $this->assertStringContainsString('LEFT', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY userId DESC', $sql);
        $this->assertStringContainsString('OFFSET 5', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testQueryStringIncludesGroupByAndHaving(): void
    {
        $stream = Json::string(json_encode([
            ['group' => 'A', 'value' => 10],
            ['group' => 'B', 'value' => 50],
        ]));

        $query = $stream->query()
            ->select('`group`')
            ->sum('value')->as('total')
            ->groupBy('group')
            ->having('total', Operator::GREATER_THAN, 20);

        $sql = (string) $query;

        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('HAVING', $sql);
    }

    public function testQueryStringIncludesIntoClause(): void
    {
        $stream = Json::string(json_encode([
            ['id' => 1],
        ]));

        $query = $stream->query()
            ->select('id')
            ->into(new FileQuery('csv(output.csv)'));

        $sql = (string) $query;

        $this->assertStringContainsString('INTO csv(output.csv)', $sql);
    }
}
