<?php

namespace UQL\Traits;

use PHPUnit\Framework\TestCase;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Stream\Json;

class SelectTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/data/products.json'));
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
        $result = $this->json->query()
            ->select('id, name, unknown')
            ->from('data.products')
            ->fetch();

        $this->assertSame(null, $result['unknown']);
    }

    public function testAliasField(): void
    {
        $result = $this->json->query()
            ->select('name, price')
            ->select('brand.name')->as('brand')
            ->from('data.products')
            ->fetch();

        self::assertSame('Brand A', $result['brand']);
    }

    public function testToDuplicateAliasField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alias "brand" already defined');

        $this->json->query()
            ->select('brand.name')->as('brand')
            ->select('name')->as('brand')
            ->from('data.products')
            ->fetch();
    }
}
