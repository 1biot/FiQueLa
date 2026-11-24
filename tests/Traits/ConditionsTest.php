<?php

namespace Traits;

use FQL\Conditions\HavingConditionGroup;
use FQL\Conditions\SimpleCondition;
use FQL\Conditions\WhereConditionGroup;
use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use FQL\Exception\UnexpectedValueException;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class ConditionsTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');
        $this->json = Json::open($jsonFile);
    }

    public function testEndGroupWithoutBeginThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('No group to end');

        $this->json->query()->endGroup();
    }

    public function testGroupedConditionsEvaluate(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 100)
            ->andGroup()
                ->or('brand.code', Operator::EQUAL, 'BRAND-B')
                ->or('brand.code', Operator::EQUAL, 'BRAND-D')
            ->endGroup();

        $results = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([3, 4, 5], array_column($results, 'id'));
    }

    public function testXorConditionApplies(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 100)
            ->xor('id', Operator::EQUAL, 2);

        $results = iterator_to_array($query->execute()->fetchAll());

        $this->assertNotEmpty($results);
    }

    public function testHavingGroupApplies(): void
    {
        $query = $this->json->query()
            ->select('brand.name')->as('brand')
            ->count('id')->as('total')
            ->from('data.products')
            ->groupBy('brand.name')
            ->havingGroup()
                ->and('total', Operator::GREATER_THAN, 1)
            ->endGroup();

        $results = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame(['Brand B'], array_column($results, 'brand'));
    }

    public function testAddWhereConditionsOverridesWhereGroup(): void
    {
        $whereGroup = new WhereConditionGroup();
        $whereGroup->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'id', Operator::EQUAL, 1)
        );

        $results = iterator_to_array(
            $this->json->query()
                ->from('data.products')
                ->addWhereConditions($whereGroup)
                ->execute()
                ->fetchAll()
        );

        $this->assertSame([1], array_column($results, 'id'));
    }

    public function testAddHavingConditionsOverridesHavingGroup(): void
    {
        $havingGroup = new HavingConditionGroup();
        $havingGroup->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'total', Operator::GREATER_THAN, 1)
        );

        $results = iterator_to_array(
            $this->json->query()
                ->select('brand.name')->as('brand')
                ->count('id')->as('total')
                ->from('data.products')
                ->groupBy('brand.name')
                ->addHavingConditions($havingGroup)
                ->execute()
                ->fetchAll()
        );

        $this->assertSame(['Brand B'], array_column($results, 'brand'));
    }

    public function testAddWhereConditionsThrowsWhenWhereAlreadyContainsConditions(): void
    {
        $whereGroup = new WhereConditionGroup();
        $whereGroup->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'id', Operator::EQUAL, 1)
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot override WHERE conditions after conditions have been added');

        $this->json->query()
            ->from('data.products')
            ->where('id', Operator::EQUAL, 2)
            ->addWhereConditions($whereGroup);
    }

    public function testAddHavingConditionsThrowsWhenHavingAlreadyContainsConditions(): void
    {
        $havingGroup = new HavingConditionGroup();
        $havingGroup->addCondition(
            LogicalOperator::AND,
            new SimpleCondition(LogicalOperator::AND, 'total', Operator::GREATER_THAN, 1)
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot override HAVING conditions after conditions have been added');

        $this->json->query()
            ->select('brand.name')->as('brand')
            ->count('id')->as('total')
            ->from('data.products')
            ->groupBy('brand.name')
            ->having('total', Operator::GREATER_THAN, 0)
            ->addHavingConditions($havingGroup);
    }

    public function testRegexpOperatorConditionApplies(): void
    {
        $results = iterator_to_array(
            $this->json->query()
                ->from('data.products')
                ->where('name', Operator::REGEXP, '^Product [A-B]$')
                ->execute()
                ->fetchAll()
        );

        $this->assertSame([1, 2], array_column($results, 'id'));
    }

    public function testNotRegexpOperatorConditionApplies(): void
    {
        $results = iterator_to_array(
            $this->json->query()
                ->from('data.products')
                ->where('name', Operator::NOT_REGEXP, '^Product [A-B]$')
                ->execute()
                ->fetchAll()
        );

        $this->assertSame([3, 4, 5], array_column($results, 'id'));
    }

    private function catalogQuery(): \FQL\Interface\Query
    {
        return Json::string(
            '{"data":[{"id":1,"price":300,"cat":"a"},{"id":2,"price":100,"cat":"b"},'
            . '{"id":3,"price":500,"cat":"a"},{"id":4,"price":200,"cat":"b"}]}'
        )->query()
            ->select('cat')
            ->count('id')->as('c')
            ->from('data')
            ->groupBy('cat');
    }

    public function testWhereAfterHavingTargetsWhere(): void
    {
        $query = $this->catalogQuery()
            ->having('c', Operator::GREATER_THAN, 0)
            ->where('price', Operator::GREATER_THAN, 100);

        $sql = (string) $query;
        $this->assertStringContainsString('WHERE price > 100', $sql);
        $this->assertStringContainsString('HAVING c > 0' . PHP_EOL, $sql . PHP_EOL);
        $this->assertSame(
            [['cat' => 'a', 'c' => 2], ['cat' => 'b', 'c' => 1]],
            iterator_to_array($query->execute()->fetchAll())
        );
    }

    public function testWhereHavingAndWhereAndChain(): void
    {
        $query = $this->catalogQuery()
            ->where('price', Operator::GREATER_THAN, 100)
            ->having('c', Operator::GREATER_THAN, 0)
            ->and('c', Operator::LESS_THAN, 10)
            ->where('id', Operator::NOT_EQUAL, 3)
            ->and('cat', Operator::EQUAL, 'a');

        $sql = (string) $query;
        $this->assertStringContainsString("WHERE price > 100 AND id != 3 AND cat = 'a'", $sql);
        $this->assertStringContainsString('HAVING c > 0 AND c < 10' . PHP_EOL, $sql . PHP_EOL);
        $this->assertSame([['cat' => 'a', 'c' => 1]], iterator_to_array($query->execute()->fetchAll()));
    }

    public function testOrAndXorContinueInHavingAfterHaving(): void
    {
        $sql = (string) $this->catalogQuery()
            ->where('price', Operator::GREATER_THAN, 100)
            ->having('c', Operator::GREATER_THAN, 5)
            ->or('c', Operator::EQUAL, 1)
            ->xor('cat', Operator::EQUAL, 'b');

        $this->assertStringContainsString('WHERE price > 100' . PHP_EOL, $sql);
        $this->assertStringContainsString("HAVING c > 5 OR c = 1 XOR cat = 'b'", $sql);
    }

    public function testWhereGroupAfterHavingTargetsWhere(): void
    {
        $query = $this->catalogQuery()
            ->having('c', Operator::GREATER_THAN, 0)
            ->whereGroup()
                ->where('id', Operator::EQUAL, 1)
                ->or('id', Operator::EQUAL, 4)
            ->endGroup()
            ->and('price', Operator::GREATER_THAN, 0);

        $sql = (string) $query;
        $this->assertStringContainsString('WHERE (id = 1 OR id = 4) AND price > 0', $sql);
        $this->assertStringContainsString('HAVING c > 0' . PHP_EOL, $sql . PHP_EOL);
        $this->assertSame(
            [['cat' => 'a', 'c' => 1], ['cat' => 'b', 'c' => 1]],
            iterator_to_array($query->execute()->fetchAll())
        );
    }

    public function testWhereInsideWhereGroupStaysInGroup(): void
    {
        $sql = (string) $this->catalogQuery()
            ->where('price', Operator::GREATER_THAN, 0)
            ->whereGroup()
                ->where('id', Operator::EQUAL, 1)
                ->or('id', Operator::EQUAL, 4)
            ->endGroup();

        $this->assertStringContainsString('WHERE price > 0 AND (id = 1 OR id = 4)', $sql);
    }
}
