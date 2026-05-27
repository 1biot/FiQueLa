<?php

namespace Query;

use FQL\Enum\Operator;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for fluent-API + WITH (CTE) interaction at the Query
 * level: register sub-queries, hand them to JOIN / UNION composition,
 * confirm results are stable.
 */
class WithTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/data/products.json'));
    }

    public function testRegisteredCteCanBeJoinedByReference(): void
    {
        $cte = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN_OR_EQUAL, 300);

        $main = $this->json->query()->with('expensive', $cte);
        $joinedQuery = $main->getCte('expensive');
        $this->assertNotNull($joinedQuery);

        $result = $this->json->query()
            ->select('p.id', 'p.name')
            ->from('data.products')
            ->innerJoin($joinedQuery, 'p')
            ->on('id', Operator::EQUAL, 'p.id')
            ->execute();

        $rows = iterator_to_array($result->fetchAll());
        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('p.id', $rows[0]);
    }

    public function testRegisteredCteCanBeUnionedByReference(): void
    {
        $cte = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN_OR_EQUAL, 300);

        $main = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::LESS_THAN_OR_EQUAL, 100)
            ->with('expensive', $cte);

        $unioned = $main->getCte('expensive');
        $this->assertNotNull($unioned);

        $rows = iterator_to_array($main->union($unioned)->execute()->fetchAll());
        // 1 cheap product (price <= 100 → product 1) + 3 expensive (price >= 300 → products 3, 4, 5).
        $this->assertCount(4, $rows);
    }
}
