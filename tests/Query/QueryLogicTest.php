<?php

namespace Query;

use FQL\Exception\QueryLogicException;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class QueryLogicTest extends TestCase
{
    private Json $stream;

    protected function setUp(): void
    {
        $data = [
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ];

        $this->stream = Json::string(json_encode($data));
    }

    public function testDistinctIsRejectedAfterGroupBy(): void
    {
        $query = $this->stream->query()->groupBy('id');

        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DISTINCT is not allowed with GROUP BY clause');

        $query->distinct();
    }

    public function testGroupByIsRejectedAfterDistinct(): void
    {
        $query = $this->stream->query()->distinct();

        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('GROUP BY is not allowed with DISTINCT clause');

        $query->groupBy('id');
    }
}
