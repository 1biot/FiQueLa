<?php

namespace Results;

use FQL\Enum\Operator;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class StreamResultsTest extends TestCase
{
    private Json $left;
    private Json $right;

    protected function setUp(): void
    {
        $leftData = [
            ['id' => 1, 'group' => 'A', 'value' => 10, 'created_at' => '2024-01-03'],
            ['id' => 2, 'group' => 'A', 'value' => 20, 'created_at' => '2024-01-20'],
            ['id' => 3, 'group' => 'B', 'value' => 50, 'created_at' => '2024-02-11'],
        ];
        $rightData = [
            ['id' => 1, 'label' => 'One'],
            ['id' => 3, 'label' => 'Three'],
            ['id' => 4, 'label' => 'Four'],
        ];

        $this->left = Json::string(json_encode($leftData));
        $this->right = Json::string(json_encode($rightData));
    }

    public function testDistinctOrderLimitOffset(): void
    {
        $query = $this->left->query()
            ->select('`group`')
            ->distinct()
            ->orderBy('group')->asc()
            ->limit(1, 1);

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([
            ['group' => 'B'],
        ], $rows);
    }

    public function testGroupByHavingWithAggregate(): void
    {
        $query = $this->left->query()
            ->select('`group`')
            ->sum('value')->as('total')
            ->groupBy('group')
            ->having('total', Operator::GREATER_THAN, 40)
            ->orderBy('total')->desc();

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([
            ['group' => 'B', 'total' => 50],
        ], $rows);
    }

    public function testGroupBySelectFunctionAlias(): void
    {
        $query = $this->left->query()
            ->formatDate('created_at', 'Y-m')->as('year_month')
            ->count('id')->as('total')
            ->groupBy('year_month')
            ->orderBy('year_month')->asc();

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([
            ['year_month' => '2024-01', 'total' => 2],
            ['year_month' => '2024-02', 'total' => 1],
        ], $rows);
    }

    public function testLeftJoinAddsNullRows(): void
    {
        $rightQuery = $this->right->query()->select('id, label');
        $query = $this->left->query()
            ->select('id')
            ->select('r.label')->as('label')
            ->leftJoin($rightQuery, 'r')->on('id', Operator::EQUAL, 'id')
            ->orderBy('id')->asc();

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertCount(3, $rows);
        $this->assertSame(['One', null, 'Three'], array_column($rows, 'label'));
    }

    public function testRightJoinIncludesUnmatchedRightRows(): void
    {
        $rightQuery = $this->right->query()->select('id, label');
        $query = $this->left->query()
            ->select('id')
            ->select('label')
            ->rightJoin($rightQuery, 'r')
            ->on('id', Operator::EQUAL, 'id');

        $rows = iterator_to_array($query->execute()->fetchAll());
        $ids = array_column($rows, 'id');

        $this->assertCount(3, $rows);
        $this->assertContains(4, $ids);
    }

    public function testFullJoinIncludesUnmatchedRightRows(): void
    {
        $rightQuery = $this->right->query()->select('id, label');
        $query = $this->left->query()
            ->select('id')
            ->select('r.label')->as('label')
            ->fullJoin($rightQuery, 'r')
            ->on('id', Operator::EQUAL, 'id');

        $rows = iterator_to_array($query->execute()->fetchAll());
        $labels = array_column($rows, 'label');

        $this->assertCount(4, $rows);
        $this->assertContains('Four', $labels);
    }
}
