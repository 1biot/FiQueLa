<?php

namespace Results;

use FQL\Enum\Operator;
use FQL\Query\FileQuery;
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

    public function testExplainPlanWithUnionShowsUnionPhase(): void
    {
        $query1 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $query2 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $rows = iterator_to_array($query1
            ->union($query2)
            ->explain()
            ->execute()
            ->fetchAll());

        $this->assertNotEmpty($rows);

        $phases = array_column($rows, 'phase');
        $this->assertContains('stream', $phases);
        $this->assertContains('union', $phases);

        $unionRow = null;
        foreach ($rows as $row) {
            if ($row['phase'] === 'union') {
                $unionRow = $row;
                break;
            }
        }

        $this->assertNotNull($unionRow);
        $this->assertSame('UNION', $unionRow['note']);
        $this->assertNull($unionRow['rows_in']);
        $this->assertNull($unionRow['time_ms']);
        $this->assertNull($unionRow['mem_peak_kb']);
    }

    public function testExplainPlanWithUnionAllShowsPhase(): void
    {
        $query1 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $query2 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $rows = iterator_to_array($query1
            ->unionAll($query2)
            ->explain()
            ->execute()
            ->fetchAll());

        $unionRow = null;
        foreach ($rows as $row) {
            if ($row['phase'] === 'union') {
                $unionRow = $row;
                break;
            }
        }

        $this->assertNotNull($unionRow);
        $this->assertSame('UNION ALL', $unionRow['note']);
    }

    public function testExplainAnalyzeWithUnionIncludesMetrics(): void
    {
        $query1 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $query2 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $rows = iterator_to_array($query1
            ->union($query2)
            ->explainAnalyze()
            ->execute()
            ->fetchAll());

        $this->assertNotEmpty($rows);

        $phases = array_column($rows, 'phase');
        $this->assertContains('stream', $phases);
        $this->assertContains('union', $phases);

        $unionRow = null;
        foreach ($rows as $row) {
            if ($row['phase'] === 'union') {
                $unionRow = $row;
                break;
            }
        }

        $this->assertNotNull($unionRow);
        $this->assertIsInt($unionRow['rows_in']);
        $this->assertIsInt($unionRow['rows_out']);
        $this->assertNotNull($unionRow['time_ms']);
        $this->assertNotNull($unionRow['duration_pct']);
        $this->assertNotNull($unionRow['mem_peak_kb']);
        $this->assertGreaterThan(0, $unionRow['rows_in']);
    }

    public function testExplainAnalyzeWithMultipleUnions(): void
    {
        $query1 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $query2 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $query3 = $this->products->query()
            ->select('id')
            ->from('data.products');

        $rows = iterator_to_array($query1
            ->union($query2)
            ->unionAll($query3)
            ->explainAnalyze()
            ->execute()
            ->fetchAll());

        $unionRows = array_values(array_filter($rows, fn($r) => in_array($r['phase'], ['union_1', 'union_2'])));
        $this->assertCount(2, $unionRows);

        $notes = array_column($unionRows, 'note');
        $this->assertContains('UNION', $notes);
        $this->assertContains('UNION ALL', $notes);
    }

    public function testExplainPlanHasMemPeakKbNull(): void
    {
        $rows = iterator_to_array($this->products->query()
            ->from('data.products')
            ->explain()
            ->execute()
            ->fetchAll());

        foreach ($rows as $row) {
            $this->assertArrayHasKey('mem_peak_kb', $row);
            $this->assertNull($row['mem_peak_kb']);
        }
    }

    public function testExplainAnalyzeHasMemPeakKb(): void
    {
        $rows = iterator_to_array($this->products->query()
            ->select('id')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 100)
            ->explainAnalyze()
            ->execute()
            ->fetchAll());

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey('mem_peak_kb', $row);
            if ($row['time_ms'] !== null) {
                $this->assertIsFloat($row['mem_peak_kb']);
                $this->assertGreaterThan(0, $row['mem_peak_kb']);
            }
        }
    }

    public function testExplainAnalyzeUnionSubPhases(): void
    {
        $query1 = $this->products->query()
            ->select('id')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 100);

        $query2 = $this->products->query()
            ->select('id')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 200);

        $rows = iterator_to_array($query1
            ->unionAll($query2)
            ->explainAnalyze()
            ->execute()
            ->fetchAll());

        $phases = array_column($rows, 'phase');

        // Main query phases
        $this->assertContains('stream', $phases);

        // Union sub-phases should be prefixed
        $this->assertContains('union_stream', $phases);
        $this->assertContains('union_where', $phases);

        // Union summary row
        $this->assertContains('union', $phases);

        // Sub-phases appear before the summary row
        $streamPos = array_search('union_stream', $phases);
        $summaryPos = array_search('union', $phases);
        $this->assertLessThan($summaryPos, $streamPos);
    }

    public function testExplainPlanWithIntoShowsIntoAsLastPhase(): void
    {
        $rows = iterator_to_array($this->products->query()
            ->select('name', 'price')
            ->from('data.products')
            ->into(new FileQuery('csv(output.csv)'))
            ->explain()
            ->execute()
            ->fetchAll());

        $this->assertNotEmpty($rows);

        $last = $rows[array_key_last($rows)];
        $this->assertSame('into', $last['phase']);
        $this->assertSame('write to csv(output.csv)', $last['note']);
        $this->assertNull($last['rows_in']);
        $this->assertNull($last['time_ms']);
        $this->assertNull($last['mem_peak_kb']);
    }

    public function testExplainAnalyzeWithIntoUsesTempFileAndKeepsExistingTarget(): void
    {
        $target = sys_get_temp_dir() . '/fiquela-explain-into-' . uniqid() . '.csv';
        file_put_contents($target, "existing\n");

        try {
            $rows = iterator_to_array($this->products->query()
                ->select('name', 'price')
                ->from('data.products')
                ->where('price', Operator::GREATER_THAN, 100)
                ->into(new FileQuery(sprintf('csv(%s)', $target)))
                ->explainAnalyze()
                ->execute()
                ->fetchAll());

            $this->assertNotEmpty($rows);

            $last = $rows[array_key_last($rows)];
            $this->assertSame('into', $last['phase']);
            $this->assertSame(sprintf('write to csv(%s)', $target), $last['note']);
            $this->assertIsInt($last['rows_in']);
            $this->assertIsInt($last['rows_out']);
            $this->assertNotNull($last['time_ms']);
            $this->assertNotNull($last['duration_pct']);
            $this->assertNotNull($last['mem_peak_kb']);

            $this->assertSame("existing\n", file_get_contents($target));
        } finally {
            if (file_exists($target)) {
                unlink($target);
            }
        }
    }
}
