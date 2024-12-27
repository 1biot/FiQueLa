<?php

namespace UQL\Traits;

use PHPUnit\Framework\TestCase;
use UQL\Enum\Operator;
use UQL\Stream\Json;

class ConditionsTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/data/products.json'));
    }

    public function testSimpleWhere(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 100);

        $results = iterator_to_array($query->fetchAll());

        $this->assertCount(3, $results);
        $this->assertEquals(200, $results[0]['price']);
    }

    public function testOrIsNull(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 200)
            ->orIsNull('description');

        $results = $query->fetchAll();
        $data = iterator_to_array($results);
        $count = $query->count();

        $this->assertCount(2, $data);
        $this->assertCount(2, $query);
        $this->assertSame($count, count($data));

        $this->assertEquals(300, $data[0]['price']);
        $this->assertEquals('Product C', $data[0]['name']);
        $this->assertEquals(400, $data[1]['price']);
        $this->assertEquals('Product D', $data[1]['name']);
    }
}
