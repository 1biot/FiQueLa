<?php

namespace Traits;

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
}
