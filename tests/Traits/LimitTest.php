<?php

namespace JQL\Traits;

use JQL\Enum\Sort;
use JQL\Json;
use PHPUnit\Framework\TestCase;

class LimitTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/products.json'));
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

    public function testLimit(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->limit(2);

        $results = iterator_to_array($query->fetchAll());

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]['id']);
        $this->assertEquals(2, $results[1]['id']);
    }
}
