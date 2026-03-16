<?php

namespace Cli\Query;

use FQL\Cli\Query\QueryResult;
use PHPUnit\Framework\TestCase;

class QueryResultTest extends TestCase
{
    public function testEmptyResult(): void
    {
        $result = new QueryResult([], [], 0, 0.1);

        $this->assertTrue($result->isEmpty());
        $this->assertEquals(0, $result->totalCount);
        $this->assertEquals(0.1, $result->elapsed);
        $this->assertNull($result->hash);
        $this->assertFalse($result->hasMorePages);
    }

    public function testResultWithData(): void
    {
        $data = [
            ['title' => 'Item 1', 'price' => 100],
            ['title' => 'Item 2', 'price' => 200],
        ];
        $result = new QueryResult($data, ['title', 'price'], 50, 0.5, 'hash123', true);

        $this->assertFalse($result->isEmpty());
        $this->assertCount(2, $result->data);
        $this->assertEquals(['title', 'price'], $result->headers);
        $this->assertEquals(50, $result->totalCount);
        $this->assertEquals(0.5, $result->elapsed);
        $this->assertEquals('hash123', $result->hash);
        $this->assertTrue($result->hasMorePages);
    }
}
