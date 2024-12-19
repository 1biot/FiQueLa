<?php

namespace JQL;

use JQL\Enum\Operator;
use JQL\Enum\Sort;
use JQL\Exceptions\FileNotFoundException;
use JQL\Exceptions\InvalidArgumentException;
use JQL\Exceptions\InvalidJson;
use PHPUnit\Framework\TestCase;

class JsonQueryTest extends TestCase
{
    private string $jsonFile;
    private string $jsonArrayFile;
    private string $invalidJsonString;

    private Json $json;
    private Json $jsonArray;

    protected function setUp(): void
    {
        $this->jsonFile = realpath(__DIR__ . '/../examples/products.json');
        $this->jsonArrayFile = realpath(__DIR__ . '/../examples/products-array.json');
        $this->invalidJsonString = '{"data": {"products": [invalid json}';

        $this->json = Json::open($this->jsonFile);
        $this->jsonArray = Json::open($this->jsonArrayFile);
    }

    public function testFromWithInvalidPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Key 'invalid' not found.");

        $this->json->query()
            ->from('data.invalid.path')
        ->fetch();
    }

    public function testOpenFile(): void
    {
        $result = Json::open($this->jsonFile)->query()
            ->from('data.products')
            ->fetch();

        $this->assertEquals('Product A', $result['name']);
    }

    public function testOpenFileNotExisted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File not found or not readable.");

        Json::open('/path/to/file/not/existed.json');
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

    public function testWhereCondition(): void
    {
        $query = $this->json->query()
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN, 100);

        $results = iterator_to_array($query->fetchAll());

        $this->assertCount(3, $results);
        $this->assertEquals(200, $results[0]['price']);
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

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Field "%s" not found', 'price'));

        $query->fetchSingle('price');
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

    public function testInvalidJson(): void
    {
        $this->expectException(InvalidJson::class);
        $this->expectExceptionMessage("Invalid JSON string");

        Json::string($this->invalidJsonString);
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
