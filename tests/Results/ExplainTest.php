<?php

namespace Results;

use FQL\Enum\Operator;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class ExplainTest extends TestCase
{
    private Json $products;
    private Json $users;

    protected function setUp(): void
    {
        $productsFile = realpath(__DIR__ . '/../../examples/data/products.json');
        $usersFile = realpath(__DIR__ . '/../../examples/data/users.json');

        $this->assertNotFalse($productsFile);
        $this->assertNotFalse($usersFile);

        $this->products = Json::open($productsFile);
        $this->users = Json::open($usersFile);
    }

    public function testExplainPlanHasNullMetrics(): void
    {
        $rows = iterator_to_array($this->products->query()
            ->from('data.products')
            ->explain()
            ->execute()
            ->fetchAll());

        $this->assertNotEmpty($rows);
        $first = $rows[0];

        $this->assertSame('stream', $first['phase']);
        $this->assertNull($first['rows_in']);
        $this->assertNull($first['rows_out']);
        $this->assertNull($first['filtered']);
        $this->assertNull($first['time_ms']);
        $this->assertNull($first['duration_pct']);
    }

    public function testExplainAnalyzeIncludesMetricsAndPhases(): void
    {
        $usersQuery = $this->users->query()
            ->from('data.users');

        $rows = iterator_to_array($this->products->query()
            ->select('brand.code')->as('brandCode')
            ->sum('price')->as('totalPrice')
            ->count('id')->as('productCount')
            ->from('data.products')
            ->leftJoin($usersQuery, 'u')
            ->on('id', Operator::EQUAL, 'id')
            ->where('price', Operator::GREATER_THAN, 100)
            ->groupBy('brand.code')
            ->having('totalPrice', Operator::GREATER_THAN, 200)
            ->orderBy('totalPrice')->desc()
            ->limit(2)
            ->explainAnalyze()
            ->execute()
            ->fetchAll());

        $this->assertNotEmpty($rows);

        $phases = array_column($rows, 'phase');
        foreach (['stream', 'join', 'where', 'group', 'having', 'sort', 'limit'] as $phase) {
            $this->assertContains($phase, $phases);
        }

        $streamRow = $rows[0];
        $this->assertSame('stream', $streamRow['phase']);
        $this->assertNull($streamRow['rows_in']);
        $this->assertSame(5, $streamRow['rows_out']);
        $this->assertNotNull($streamRow['time_ms']);
        $this->assertNotNull($streamRow['duration_pct']);

        foreach ($rows as $row) {
            if ($row['rows_in'] !== null) {
                $this->assertIsInt($row['rows_in']);
                $this->assertIsInt($row['rows_out']);
                $this->assertIsInt($row['filtered']);
            }

            if ($row['time_ms'] !== null) {
                $this->assertNotNull($row['duration_pct']);
            }
        }
    }

    public function testExplainPlanJoinWithoutConditionShowsNote(): void
    {
        $rows = iterator_to_array($this->products->query()
            ->from('data.products')
            ->leftJoin($this->users->query()->from('data.users'), 'u')
            ->explain()
            ->execute()
            ->fetchAll());

        $this->assertNotEmpty($rows);

        $joinRow = null;
        foreach ($rows as $row) {
            if ($row['phase'] === 'join') {
                $joinRow = $row;
                break;
            }
        }

        $this->assertNotNull($joinRow);
        $this->assertStringContainsString('[No Condition]', $joinRow['note']);
    }
}
