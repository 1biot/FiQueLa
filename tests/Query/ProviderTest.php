<?php

namespace Query;

use FQL\Enum\Type;
use PHPUnit\Framework\TestCase;
use FQL\Enum\Operator;
use FQL\Exception\InvalidArgumentException;
use FQL\Stream\Json;

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

        $results = $query->execute();
        $data = iterator_to_array($results->fetchAll());

        $this->assertCount(5, $data);
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals('Product A', $data[0]['name']);
    }

    public function testFetch(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('id', Operator::EQUAL, 2);

        $results = $query->execute();
        $data = $results->fetch();

        $this->assertNotNull($data);
        $this->assertEquals('Product B', $data['name']);
    }

    public function testFetchSingle(): void
    {
        $query = $this->json->query()
            ->select('name')
            ->from('data.products')
            ->where('price', Operator::EQUAL, 100);

        $results = $query->execute();
        $existedField = $results->fetchSingle('name');

        $this->assertEquals('Product A', $existedField);

        $nonSelectedField = $results->fetchSingle('price');
        $this->assertEquals(null, $nonSelectedField);
    }

    public function testOrderBy(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->orderBy('price')->desc();

        $results = $query->execute();
        $data = iterator_to_array($results->fetchAll());

        $this->assertEquals(4, $data[0]['id']);
        $this->assertEquals(5, $data[1]['id']);
        $this->assertEquals(3, $data[2]['id']);
        $this->assertEquals(2, $data[3]['id']);
        $this->assertEquals(1, $data[4]['id']);

        $query->orderBy('name')->desc();

        $results = $query->execute();
        $data = iterator_to_array($results->fetchAll());


        $this->assertEquals(5, $data[0]['id']);
        $this->assertEquals(4, $data[1]['id']);
        $this->assertEquals(3, $data[2]['id']);
        $this->assertEquals(2, $data[3]['id']);
        $this->assertEquals(1, $data[4]['id']);
    }

    public function testCount(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::LESS_THAN, 300);

        $this->assertEquals(2, $query->execute()->count());
    }

    public function testSum(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 100);

        $sum = $query->execute()->sum('price');

        $this->assertEquals(1300, $sum, 'The sum of prices greater than 100 should be 500.');
    }

    public function testJsonIsArray(): void
    {
        $query = $this->jsonArray->query()
            ->where('price', Operator::GREATER_THAN, 100);

        $results = $query->execute();
        $data = iterator_to_array($results->fetchAll());

        $this->assertCount(3, $data);
        $this->assertEquals(200, $data[0]['price']);
        $this->assertEquals('Product B', $data[0]['name']);
        $this->assertEquals(300, $data[1]['price']);
        $this->assertEquals('Product C', $data[1]['name']);
        $this->assertEquals(400, $data[2]['price']);
        $this->assertEquals('Product D', $data[2]['name']);
    }

    public function testUnknownFieldSelect(): void
    {
        $result = $this->json->query()
            ->select('id, name, unknown')
            ->from('data.products')
            ->execute()
            ->fetch();

        $this->assertSame(null, $result['unknown']);
    }

    public function testLimit(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->limit(2);

        $results = $query->execute();
        $data = iterator_to_array($results->fetchAll());

        $this->assertCount(2, $data);
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals(2, $data[1]['id']);
    }

    public function testFromWithInvalidPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Key 'invalid' not found.");

        $this->json->query()
            ->from('data.invalid.path')
            ->execute()
            ->fetch();
    }

    public function testOrIsNull(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 200)
            ->or('description', Operator::IS, Type::NULL);

        $results = $query->execute();
        $data = iterator_to_array($results->fetchAll());

        $count = $results->count();

        $this->assertCount(3, $data);
        $this->assertCount(3, $results);
        $this->assertSame($count, count($data));

        $this->assertEquals(300, $data[0]['price']);
        $this->assertEquals('Product C', $data[0]['name']);
        $this->assertEquals(400, $data[1]['price']);
        $this->assertEquals('Product D', $data[1]['name']);
    }

    public function testAnonymousClassDto(): void
    {
        $query = $this->json->query()
            ->select('id, name, price')
            ->select('brand.name')->as('brand')
            ->select('categories[]->name')->as('categories')
            ->select('categories[]->id')->as('categoryIds')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 200);

        $dto = new class {
            public int $id;
            public string $name;
            public int $price;
            public string $brand;
            public array $categories;
            public array $categoryIds;
        };

        $results = $query->execute();
        $data = iterator_to_array($results->fetchAll($dto::class));

        $this->assertCount(3, $data);
        $this->assertInstanceOf($dto::class, $data[0]);
        $this->assertInstanceOf($dto::class, $data[1]);

        $this->assertEquals('Brand D', $data[0]->brand);
        $this->assertEquals('Brand B', $data[1]->brand);

        $this->assertIsList($data[0]->categories);
        $this->assertIsList($data[1]->categories);

        $this->assertIsList($data[0]->categoryIds);
        $this->assertIsList($data[1]->categoryIds);
    }
}
