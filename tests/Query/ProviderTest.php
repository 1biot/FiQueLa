<?php

namespace UQL\Query;

use PHPUnit\Framework\TestCase;
use UQL\Enum\Operator;
use UQL\Enum\Sort;
use UQL\Stream\Json;

class ProviderTest extends TestCase
{
    private Json $json;
    private Json $jsonArray;

    protected function setUp(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');
        $jsonArrayFile = realpath(__DIR__ . '/../../examples/data/products-array.json');

        $this->json = Json::open($jsonFile);
        $this->jsonArray = Json::open($jsonArrayFile);
    }

    public function testFetchAll(): void
    {
        $query = $this->json->query()
            ->from('data.products');

        $results = iterator_to_array($query->fetchAll());

        $this->assertCount(4, $results);
        $this->assertEquals(1, $results[0]['id']);
        $this->assertEquals('Product A', $results[0]['name']);
    }

    public function testFetch(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('id', Operator::EQUAL, 2);

        $result = $query->fetch();

        $this->assertNotNull($result);
        $this->assertEquals('Product B', $result['name']);
    }

    public function testFetchSingle(): void
    {
        $query = $this->json->query()
            ->select('name')
            ->from('data.products')
            ->where('price', Operator::EQUAL, 100);

        $existedField = $query->fetchSingle('name');
        $this->assertEquals('Product A', $existedField);

        $nonSelectedField = $query->fetchSingle('price');
        $this->assertEquals(null, $nonSelectedField);
    }

    public function testOrderBy(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->orderBy('price', Sort::DESC);

        $results = iterator_to_array($query->fetchAll());

        $this->assertEquals(4, $results[0]['id']);
        $this->assertEquals(3, $results[1]['id']);
        $this->assertEquals(2, $results[2]['id']);
        $this->assertEquals(1, $results[3]['id']);
    }

    public function testCount(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::LESS_THAN, 300);

        $this->assertEquals(2, $query->count());
    }

    public function testSum(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 100);

        $sum = $query->sum('price');

        $this->assertEquals(900, $sum, 'The sum of prices greater than 100 should be 500.');
    }

    public function testJsonIsArray(): void
    {
        $query = $this->jsonArray->query()
            ->where('price', Operator::GREATER_THAN, 100);

        $results = iterator_to_array($query->fetchAll());

        $this->assertCount(3, $results);
        $this->assertEquals(200, $results[0]['price']);
        $this->assertEquals('Product B', $results[0]['name']);
        $this->assertEquals(300, $results[1]['price']);
        $this->assertEquals('Product C', $results[1]['name']);
        $this->assertEquals(400, $results[2]['price']);
        $this->assertEquals('Product D', $results[2]['name']);
    }
}
