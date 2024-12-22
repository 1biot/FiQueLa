<?php

namespace UQL\Traits;

use PHPUnit\Framework\TestCase;
use UQL\Stream\Json;

class LimitTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/data/products.json'));
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
