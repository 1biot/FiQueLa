<?php

namespace Traits;

use UQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class SortableTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');
        $this->json = Json::open($jsonFile);
    }

    public function testSortByDesc(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->orderBy('price')->desc();

        $results = iterator_to_array($query->execute()->fetchAll());

        $this->assertEquals(4, $results[0]['id']);
        $this->assertEquals(5, $results[1]['id']);
        $this->assertEquals(3, $results[2]['id']);
        $this->assertEquals(2, $results[3]['id']);
        $this->assertEquals(1, $results[4]['id']);
    }

    public function testSortByAsc(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->orderBy('price')->asc();

        $results = iterator_to_array($query->execute()->fetchAll());

        $this->assertEquals(1, $results[0]['id']);
        $this->assertEquals(2, $results[1]['id']);
        $this->assertEquals(3, $results[2]['id']);
        $this->assertEquals(4, $results[3]['id']);
    }
}
