<?php

namespace JQL\Traits;

use JQL\Exceptions\InvalidArgumentException;
use JQL\Json;
use PHPUnit\Framework\TestCase;

class SelectTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/products.json'));
    }

    public function testSimpleSelect(): void
    {
        $result = $this->json->query()
            ->select('id, name, price')
            ->from('data.products')
            ->fetch();


        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Product A', $result['name']);
        $this->assertEquals(100, $result['price']);
    }

    public function testMultipleSelect(): void
    {
        $result = $this->json->query()
            ->select('id')
            ->select('name')
            ->select('price')
            ->from('data.products')
            ->fetch();

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Product A', $result['name']);
        $this->assertEquals(100, $result['price']);
    }

    public function testUnknownFieldSelect(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "unknown" not found');

        $this->json->query()
            ->select('id, name, unknown')
            ->from('data.products')
            ->fetch();
    }
}
