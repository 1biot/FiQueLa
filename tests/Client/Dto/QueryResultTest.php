<?php

namespace Client\Dto;

use FQL\Client\Dto\Pagination;
use FQL\Client\Dto\QueryResult;
use PHPUnit\Framework\TestCase;

class QueryResultTest extends TestCase
{
    public function testFromArray(): void
    {
        $result = QueryResult::fromArray([
            'query' => 'SELECT * FROM items',
            'file' => 'data.xml',
            'hash' => 'abc123',
            'data' => [
                ['title' => 'Item 1', 'price' => 100],
                ['title' => 'Item 2', 'price' => 200],
            ],
            'elapsed' => 0.123,
            'pagination' => [
                'page' => 1,
                'pageCount' => 1,
                'itemCount' => 2,
                'itemsPerPage' => 1000,
                'offset' => 0,
            ],
        ]);

        $this->assertEquals('SELECT * FROM items', $result->query);
        $this->assertEquals('data.xml', $result->file);
        $this->assertEquals('abc123', $result->hash);
        $this->assertCount(2, $result->data);
        $this->assertEquals(0.123, $result->elapsed);
        $this->assertInstanceOf(Pagination::class, $result->pagination);
        $this->assertEquals(1, $result->pagination->page);
    }

    public function testIsEmpty(): void
    {
        $empty = QueryResult::fromArray(['data' => []]);
        $this->assertTrue($empty->isEmpty());

        $notEmpty = QueryResult::fromArray(['data' => [['a' => 1]]]);
        $this->assertFalse($notEmpty->isEmpty());
    }

    public function testGetHeaders(): void
    {
        $result = QueryResult::fromArray([
            'data' => [
                ['title' => 'Item 1', 'price' => 100, 'quantity' => 5],
            ],
        ]);

        $this->assertEquals(['title', 'price', 'quantity'], $result->getHeaders());
    }

    public function testGetHeadersEmpty(): void
    {
        $result = QueryResult::fromArray(['data' => []]);
        $this->assertEquals([], $result->getHeaders());
    }

    public function testFromArrayDefaults(): void
    {
        $result = QueryResult::fromArray([]);

        $this->assertEquals('', $result->query);
        $this->assertEquals('', $result->file);
        $this->assertEquals('', $result->hash);
        $this->assertEmpty($result->data);
        $this->assertEquals(0.0, $result->elapsed);
        $this->assertInstanceOf(Pagination::class, $result->pagination);
    }
}
