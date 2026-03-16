<?php

namespace Cli\Query;

use Client\MockTransport;
use FQL\Cli\Query\ApiQueryExecutor;
use FQL\Client\FiQueLaClient;
use FQL\Client\Response;
use PHPUnit\Framework\TestCase;

class ApiQueryExecutorTest extends TestCase
{
    private MockTransport $transport;
    private FiQueLaClient $client;
    private ApiQueryExecutor $executor;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->client = new FiQueLaClient('https://api.example.com', 'test-token', $this->transport);
        $this->executor = new ApiQueryExecutor($this->client, 'test-server');
    }

    public function testGetModeName(): void
    {
        $this->assertEquals('API', $this->executor->getModeName());
    }

    public function testGetServerName(): void
    {
        $this->assertEquals('test-server', $this->executor->getServerName());
    }

    public function testExecute(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            'query' => 'SELECT * FROM items',
            'file' => 'data.csv',
            'hash' => 'abc123',
            'data' => [
                ['title' => 'Item 1', 'price' => 100],
                ['title' => 'Item 2', 'price' => 200],
            ],
            'elapsed' => 45.6,
            'pagination' => [
                'page' => 1,
                'pageCount' => 1,
                'itemCount' => 2,
                'itemsPerPage' => 1000,
                'offset' => 0,
            ],
        ])));

        $result = $this->executor->execute('SELECT * FROM items');

        $this->assertCount(2, $result->data);
        $this->assertEquals(['title', 'price'], $result->headers);
        $this->assertEquals(2, $result->totalCount);
        $this->assertFalse($result->hasMorePages);
    }

    public function testExecuteWithPagination(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            'query' => 'SELECT * FROM items',
            'file' => 'data.csv',
            'hash' => 'abc123',
            'data' => array_fill(0, 25, ['col' => 'value']),
            'elapsed' => 100.0,
            'pagination' => [
                'page' => 1,
                'pageCount' => 5,
                'itemCount' => 125,
                'itemsPerPage' => 25,
                'offset' => 0,
            ],
        ])));

        $result = $this->executor->execute('SELECT * FROM items', 1, 25);

        $this->assertTrue($result->hasMorePages);
        $this->assertEquals(125, $result->totalCount);
        $this->assertEquals('abc123', $result->hash);
    }

    public function testExecuteAllSinglePage(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            'query' => 'SELECT * FROM items',
            'file' => 'data.csv',
            'hash' => 'abc123',
            'data' => [['col' => 'value']],
            'elapsed' => 10.0,
            'pagination' => [
                'page' => 1,
                'pageCount' => 1,
                'itemCount' => 1,
                'itemsPerPage' => 1000,
                'offset' => 0,
            ],
        ])));

        $result = $this->executor->executeAll('SELECT * FROM items');

        $this->assertCount(1, $result->data);
        $this->assertFalse($result->hasMorePages);
    }

    public function testExecuteAllMultiplePages(): void
    {
        // First call: query (returns paginated result)
        $this->transport->addResponse(new Response(200, [], json_encode([
            'query' => 'SELECT * FROM items',
            'file' => 'data.csv',
            'hash' => 'export-hash',
            'data' => [['col' => 'page1']],
            'elapsed' => 10.0,
            'pagination' => [
                'page' => 1,
                'pageCount' => 3,
                'itemCount' => 3000,
                'itemsPerPage' => 1000,
                'offset' => 0,
            ],
        ])));

        // Second call: export (returns all data)
        $allData = array_fill(0, 3, ['col' => 'exported']);
        $this->transport->addResponse(new Response(200, [], json_encode($allData)));

        $result = $this->executor->executeAll('SELECT * FROM items');

        $this->assertCount(3, $result->data);
        $this->assertEquals('export-hash', $result->hash);

        // Verify export was called
        $this->assertEquals(2, $this->transport->getRequestCount());
        $lastRequest = $this->transport->getLastRequest();
        $this->assertStringContainsString('/api/v1/export/export-hash', $lastRequest['url']);
    }

    public function testElapsedConversion(): void
    {
        // API returns elapsed in milliseconds, executor should convert to seconds
        $this->transport->addResponse(new Response(200, [], json_encode([
            'query' => 'SELECT *',
            'file' => 'f',
            'hash' => 'h',
            'data' => [['a' => 1]],
            'elapsed' => 1500.0, // 1500ms
            'pagination' => [
                'page' => 1,
                'pageCount' => 1,
                'itemCount' => 1,
                'itemsPerPage' => 1000,
                'offset' => 0,
            ],
        ])));

        $result = $this->executor->execute('SELECT *');

        $this->assertEquals(1.5, $result->elapsed); // 1500ms = 1.5s
    }
}
