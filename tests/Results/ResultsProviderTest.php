<?php

namespace Results;

use PHPUnit\Framework\TestCase;
use UQL\Results\Cache;

class ResultsProviderTest extends TestCase
{
    private Cache $proxy;

    protected function setUp(): void
    {
        $this->proxy = new Cache([
            [
                'id' => 1,
                'name' => 'Product A',
                'price' => 100
            ],
            [
                'id' => 2,
                'name' => 'Product B',
                'price' => 200
            ],
            [
                'id' => 3,
                'name' => 'Product C',
                'price' => 300
            ],
            [
                'id' => 4,
                'name' => 'Product D',
                'price' => 400
            ],
            [
                'id' => 5,
                'name' => 'Product E',
                'price' => 500
            ]
        ]);
    }

    public function testFetchAll(): void
    {
        $results = $this->proxy->fetchAll();
        $data = iterator_to_array($results);

        $this->assertCount(5, $data);
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals('Product A', $data[0]['name']);
    }

    public function testFetch(): void
    {
        $results = $this->proxy->fetch();

        $this->assertEquals(1, $results['id']);
        $this->assertEquals('Product A', $results['name']);
    }

    public function testFetchNth(): void
    {
        $results = iterator_to_array($this->proxy->fetchNth(2))[0];

        $this->assertNotNull($results);
        $this->assertEquals('Product B', $results['name']);
    }

    public function testFetchSingle(): void
    {
        $existedField = $this->proxy->fetchSingle('name');
        $this->assertEquals('Product A', $existedField);
    }

    public function testCount(): void
    {
        $this->assertEquals(5, $this->proxy->count());
    }

    public function testSum(): void
    {
        $this->assertEquals(1500, $this->proxy->sum('price'));
    }

    public function testAvg(): void
    {
        $this->assertEquals(300, $this->proxy->avg('price'));
    }

    public function testMin(): void
    {
        $this->assertEquals(100, $this->proxy->min('price'));
    }

    public function testMax(): void
    {
        $this->assertEquals(500, $this->proxy->max('price'));
    }

    public function testGetIterator(): void
    {
        $this->assertInstanceOf(\Iterator::class, $this->proxy->getIterator());
        $this->assertInstanceOf(\ArrayIterator::class, $this->proxy->getIterator());
    }
}
