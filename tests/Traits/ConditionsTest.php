<?php

namespace Traits;

use PHPUnit\Framework\TestCase;
use UQL\Enum\LogicalOperator;
use UQL\Enum\Operator;
use UQL\Query\TestProvider;
use UQL\Stream\Json;

class ConditionsTest extends TestCase
{
    /** @var TestProvider $query */
    private TestProvider $query;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->query = new TestProvider();
    }

    public function testWhereConditions(): void
    {
        $this->query->resetConditions()
            ->where('id', Operator::EQUAL, 1)
            ->where('price', Operator::GREATER_THAN, 100)
            ->where('price', Operator::LESS_THAN, 200)
            ->and('name', Operator::CONTAINS, 'Product');

        $this->assertEquals([
            0 => [
                'operator' => Operator::EQUAL,
                'value' => 1,
                'key' => 'id',
                'type' => LogicalOperator::AND,
            ],
            1 => [
                'operator' => Operator::GREATER_THAN,
                'value' => 100,
                'key' => 'price',
                'type' => LogicalOperator::AND,
            ],
            2 => [
                'operator' => Operator::LESS_THAN,
                'value' => 200,
                'key' => 'price',
                'type' => LogicalOperator::AND,
            ],
            3 => [
                'operator' => Operator::CONTAINS,
                'value' => 'Product',
                'key' => 'name',
                'type' => LogicalOperator::AND,
            ],
        ], $this->query->getConditions('where'));
    }

    public function testOrConditions(): void
    {
        $this->query->resetConditions()
            ->or('id', Operator::EQUAL, 1)
            ->or('price', Operator::GREATER_THAN, 100)
            ->or('price', Operator::LESS_THAN, 200)
            ->or('name', Operator::CONTAINS, 'Product');

        $this->assertEquals([
            0 => [
                'operator' => Operator::EQUAL,
                'value' => 1,
                'key' => 'id',
                'type' => LogicalOperator::OR,
            ],
            1 => [
                'operator' => Operator::GREATER_THAN,
                'value' => 100,
                'key' => 'price',
                'type' => LogicalOperator::OR,
            ],
            2 => [
                'operator' => Operator::LESS_THAN,
                'value' => 200,
                'key' => 'price',
                'type' => LogicalOperator::OR,
            ],
            3 => [
                'operator' => Operator::CONTAINS,
                'value' => 'Product',
                'key' => 'name',
                'type' => LogicalOperator::OR,
            ],
        ], $this->query->getConditions('where'));
    }

    public function testAndOrConditions(): void
    {
        $this->query->resetConditions()
            ->where('id', Operator::EQUAL, 1)
            ->and('price', Operator::GREATER_THAN, 100)
            ->or('price', Operator::LESS_THAN, 200)
            ->and('name', Operator::CONTAINS, 'Product');

        $this->assertEquals([
            0 => [
                'operator' => Operator::EQUAL,
                'value' => 1,
                'key' => 'id',
                'type' => LogicalOperator::AND,
            ],
            1 => [
                'operator' => Operator::GREATER_THAN,
                'value' => 100,
                'key' => 'price',
                'type' => LogicalOperator::AND,
            ],
            2 => [
                'operator' => Operator::LESS_THAN,
                'value' => 200,
                'key' => 'price',
                'type' => LogicalOperator::OR,
            ],
            3 => [
                'operator' => Operator::CONTAINS,
                'value' => 'Product',
                'key' => 'name',
                'type' => LogicalOperator::AND,
            ],
        ], $this->query->getConditions('where'));
    }

    public function testWhereConditionsGrouping(): void
    {
        $this->query->resetConditions()
            ->where('id', Operator::EQUAL, 1)
            ->and('price', Operator::GREATER_THAN, 100)
            ->andGroup()
                ->where('price', Operator::LESS_THAN, 200)
                ->or('name', Operator::CONTAINS, 'Product')
            ->endGroup();

        $this->assertEquals([
            0 => [
                'operator' => Operator::EQUAL,
                'value' => 1,
                'key' => 'id',
                'type' => LogicalOperator::AND,
            ],
            1 => [
                'operator' => Operator::GREATER_THAN,
                'value' => 100,
                'key' => 'price',
                'type' => LogicalOperator::AND,
            ],
            2 => [
                'type' => LogicalOperator::AND,
                'group' => [
                    0 => [
                        'operator' => Operator::LESS_THAN,
                        'value' => 200,
                        'key' => 'price',
                        'type' => LogicalOperator::AND,
                    ],
                    1 => [
                        'operator' => Operator::CONTAINS,
                        'value' => 'Product',
                        'key' => 'name',
                        'type' => LogicalOperator::OR,
                    ],
                ],
            ],
        ], $this->query->getConditions('where'));
    }

    public function testOrConditionsGrouping(): void
    {
        $this->query->resetConditions()
            ->where('id', Operator::EQUAL, 1)
            ->and('price', Operator::GREATER_THAN, 100)
            ->orGroup()
                ->where('price', Operator::LESS_THAN, 400)
                ->and('name', Operator::CONTAINS, 'Product 2')
            ->endGroup();

        $this->assertEquals([
            0 => [
                'operator' => Operator::EQUAL,
                'value' => 1,
                'key' => 'id',
                'type' => LogicalOperator::AND,
            ],
            1 => [
                'operator' => Operator::GREATER_THAN,
                'value' => 100,
                'key' => 'price',
                'type' => LogicalOperator::AND,
            ],
            2 => [
                'type' => LogicalOperator::OR,
                'group' => [
                    0 => [
                        'operator' => Operator::LESS_THAN,
                        'value' => 400,
                        'key' => 'price',
                        'type' => LogicalOperator::AND,
                    ],
                    1 => [
                        'operator' => Operator::CONTAINS,
                        'value' => 'Product 2',
                        'key' => 'name',
                        'type' => LogicalOperator::AND,
                    ],
                ],
            ]
        ], $this->query->getConditions('where'));
    }
}
