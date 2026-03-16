<?php

namespace Client;

use FQL\Client\Dto\AuthToken;
use FQL\Client\Dto\FileSchema;
use FQL\Client\Dto\HistoryEntry;
use FQL\Client\Dto\QueryResult;
use FQL\Client\Exception\AuthenticationException;
use FQL\Client\Exception\NotFoundException;
use FQL\Client\Exception\ServerException;
use FQL\Client\Exception\ValidationException;
use FQL\Client\FiQueLaClient;
use FQL\Client\Response;
use PHPUnit\Framework\TestCase;

class FiQueLaClientTest extends TestCase
{
    private MockTransport $transport;
    private FiQueLaClient $client;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->client = new FiQueLaClient('https://api.example.com', 'test-token', $this->transport);
    }

    // -------------------------------------------------------
    // Token management
    // -------------------------------------------------------

    public function testTokenManagement(): void
    {
        $this->assertTrue($this->client->hasToken());
        $this->assertEquals('test-token', $this->client->getToken());

        $this->client->setToken('new-token');
        $this->assertEquals('new-token', $this->client->getToken());
    }

    public function testHasTokenWhenEmpty(): void
    {
        $client = new FiQueLaClient('https://api.example.com', null, $this->transport);
        $this->assertFalse($client->hasToken());
    }

    public function testGetBaseUrl(): void
    {
        $this->assertEquals('https://api.example.com', $this->client->getBaseUrl());
    }

    public function testBaseUrlTrailingSlashRemoved(): void
    {
        $client = new FiQueLaClient('https://api.example.com/', null, $this->transport);
        $this->assertEquals('https://api.example.com', $client->getBaseUrl());
    }

    // -------------------------------------------------------
    // Auth
    // -------------------------------------------------------

    public function testLogin(): void
    {
        $this->transport->addResponse(new Response(200, [], '{"token":"jwt-token-123"}'));

        $result = $this->client->login('testuser', 'TestPass123!');

        $this->assertInstanceOf(AuthToken::class, $result);
        $this->assertEquals('jwt-token-123', $result->token);

        $request = $this->transport->getLastRequest();
        $this->assertEquals('POST', $request['method']);
        $this->assertStringContainsString('/api/auth/login', $request['url']);

        $body = json_decode($request['body'], true);
        $this->assertEquals('testuser', $body['username']);
        $this->assertEquals('TestPass123!', $body['password']);
    }

    public function testLoginInvalidCredentials(): void
    {
        $this->transport->addResponse(new Response(401, [], '{"error":"Invalid credentials"}'));

        $this->expectException(AuthenticationException::class);
        $this->client->login('wrong', 'wrong');
    }

    public function testRevoke(): void
    {
        $this->transport->addResponse(new Response(200, [], '{"revoked":"new-jwt-token-456"}'));

        $result = $this->client->revoke('testuser', 'TestPass123!');

        $this->assertInstanceOf(AuthToken::class, $result);
        $this->assertEquals('new-jwt-token-456', $result->token);
    }

    // -------------------------------------------------------
    // Health
    // -------------------------------------------------------

    public function testPingSuccess(): void
    {
        $this->transport->addResponse(new Response(200, [], 'pong'));

        $this->assertTrue($this->client->ping());
    }

    public function testPingFailure(): void
    {
        $this->transport->addResponse(new Response(500, [], 'error'));

        $this->assertFalse($this->client->ping());
    }

    // -------------------------------------------------------
    // Files
    // -------------------------------------------------------

    public function testListFiles(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            [
                'uuid' => 'uuid-1',
                'name' => 'file1.xml',
                'encoding' => 'utf-8',
                'type' => 'xml',
                'size' => 1000,
                'delimiter' => null,
                'query' => null,
                'count' => 50,
                'columns' => [],
            ],
            [
                'uuid' => 'uuid-2',
                'name' => 'file2.csv',
                'encoding' => 'utf-8',
                'type' => 'csv',
                'size' => 2000,
                'delimiter' => ',',
                'query' => null,
                'count' => 100,
                'columns' => [],
            ],
        ])));

        $files = $this->client->listFiles();

        $this->assertCount(2, $files);
        $this->assertInstanceOf(FileSchema::class, $files[0]);
        $this->assertEquals('file1.xml', $files[0]->name);
        $this->assertEquals('file2.csv', $files[1]->name);
    }

    public function testGetFile(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            'uuid' => 'uuid-1',
            'name' => 'file1.xml',
            'encoding' => 'utf-8',
            'type' => 'xml',
            'size' => 1000,
            'delimiter' => null,
            'query' => 'channel.items',
            'count' => 50,
            'columns' => [
                [
                    'column' => 'title',
                    'types' => ['string' => 50],
                    'totalRows' => 50,
                    'totalTypes' => 1,
                    'dominant' => 'string',
                    'suspicious' => false,
                    'confidence' => 1.0,
                    'completeness' => 1.0,
                    'constant' => false,
                    'isEnum' => false,
                    'isUnique' => true,
                ],
            ],
        ])));

        $file = $this->client->getFile('uuid-1');

        $this->assertInstanceOf(FileSchema::class, $file);
        $this->assertEquals('uuid-1', $file->uuid);
        $this->assertCount(1, $file->columns);
        $this->assertEquals('title', $file->columns[0]->column);
    }

    public function testGetFileNotFound(): void
    {
        $this->transport->addResponse(new Response(404, [], '{"error":"File not found"}'));

        $this->expectException(NotFoundException::class);
        $this->client->getFile('nonexistent-uuid');
    }

    public function testUpdateFile(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            'schema' => [
                'uuid' => 'uuid-1',
                'name' => 'file1.xml',
                'encoding' => 'windows-1250',
                'type' => 'xml',
                'size' => 1000,
                'delimiter' => null,
                'query' => 'channel.items',
                'count' => 50,
                'columns' => [],
            ],
        ])));

        $file = $this->client->updateFile('uuid-1', 'windows-1250', null, 'channel.items');

        $this->assertEquals('windows-1250', $file->encoding);

        $request = $this->transport->getLastRequest();
        $body = json_decode($request['body'], true);
        $this->assertEquals('windows-1250', $body['encoding']);
        $this->assertEquals('channel.items', $body['query']);
    }

    public function testDeleteFile(): void
    {
        $this->transport->addResponse(new Response(200, [], '{"message":"File deleted"}'));

        $result = $this->client->deleteFile('uuid-1');
        $this->assertTrue($result);

        $request = $this->transport->getLastRequest();
        $this->assertEquals('DELETE', $request['method']);
    }

    // -------------------------------------------------------
    // Query
    // -------------------------------------------------------

    public function testQuery(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            'query' => 'SELECT * FROM channel.item',
            'file' => 'file.xml',
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

        $result = $this->client->query('SELECT * FROM channel.item', 'file.xml');

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(2, $result->data);
        $this->assertEquals('abc123', $result->hash);
        $this->assertFalse($result->pagination->hasMultiplePages());
    }

    public function testQueryWithPagination(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            'query' => 'SELECT * FROM items',
            'file' => 'big.csv',
            'hash' => 'hash456',
            'data' => array_fill(0, 10, ['col' => 'value']),
            'elapsed' => 100.0,
            'pagination' => [
                'page' => 1,
                'pageCount' => 5,
                'itemCount' => 50,
                'itemsPerPage' => 10,
                'offset' => 0,
            ],
        ])));

        $result = $this->client->query('SELECT * FROM items', 'big.csv', 10, 1);

        $this->assertTrue($result->pagination->hasMultiplePages());
        $this->assertEquals(5, $result->pagination->pageCount);

        $request = $this->transport->getLastRequest();
        $body = json_decode($request['body'], true);
        $this->assertEquals(10, $body['limit']);
        $this->assertEquals(1, $body['page']);
    }

    public function testQueryWithRefresh(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            'query' => 'SELECT *',
            'file' => 'f.csv',
            'hash' => 'h',
            'data' => [],
            'elapsed' => 0,
            'pagination' => ['page' => 1, 'pageCount' => 1, 'itemCount' => 0, 'itemsPerPage' => 0, 'offset' => 0],
        ])));

        $this->client->query('SELECT *', 'f.csv', null, null, true);

        $request = $this->transport->getLastRequest();
        $body = json_decode($request['body'], true);
        $this->assertTrue($body['refresh']);
    }

    public function testQueryValidationError(): void
    {
        $this->transport->addResponse(new Response(422, [], '{"error":"Invalid query syntax"}'));

        $this->expectException(ValidationException::class);
        $this->client->query('INVALID QUERY');
    }

    // -------------------------------------------------------
    // Export
    // -------------------------------------------------------

    public function testExportJson(): void
    {
        $jsonData = json_encode([['title' => 'Item 1'], ['title' => 'Item 2']]);
        $this->transport->addResponse(new Response(200, [], $jsonData));

        $result = $this->client->export('abc123', 'json');

        $this->assertEquals($jsonData, $result);

        $request = $this->transport->getLastRequest();
        $this->assertEquals('GET', $request['method']);
        $this->assertStringContainsString('/api/v1/export/abc123', $request['url']);
        $this->assertStringContainsString('format=json', $request['url']);
    }

    public function testExportCsv(): void
    {
        $csvData = "title,price\nItem 1,100\nItem 2,200";
        $this->transport->addResponse(new Response(200, [], $csvData));

        $result = $this->client->export('abc123', 'csv');
        $this->assertEquals($csvData, $result);
    }

    public function testExportWithDelimiter(): void
    {
        $this->transport->addResponse(new Response(200, [], 'data'));

        $this->client->export('abc123', 'csv', ';');

        $request = $this->transport->getLastRequest();
        $this->assertStringContainsString('delimiter=%3B', $request['url']);
    }

    // -------------------------------------------------------
    // History
    // -------------------------------------------------------

    public function testGetHistory(): void
    {
        $this->transport->addResponse(new Response(200, [], json_encode([
            [
                'created_at' => '2023-10-01T12:00:00Z',
                'query' => 'select * from items',
                'runs' => 'SELECT * FROM [xml](file.xml).items',
            ],
            [
                'created_at' => '2023-10-01T12:01:00Z',
                'query' => 'select title from items',
                'runs' => 'SELECT title FROM [xml](file.xml).items',
            ],
        ])));

        $history = $this->client->getHistory();

        $this->assertCount(2, $history);
        $this->assertInstanceOf(HistoryEntry::class, $history[0]);
        $this->assertEquals('select * from items', $history[0]->query);
    }

    public function testGetHistoryByDate(): void
    {
        $this->transport->addResponse(new Response(200, [], '[]'));

        $this->client->getHistory('2023-10-01');

        $request = $this->transport->getLastRequest();
        $this->assertStringContainsString('/api/v1/history/2023-10-01', $request['url']);
    }

    // -------------------------------------------------------
    // Authorization header
    // -------------------------------------------------------

    public function testAuthorizationHeaderSent(): void
    {
        $this->transport->addResponse(new Response(200, [], '[]'));

        $this->client->listFiles();

        $request = $this->transport->getLastRequest();
        $this->assertEquals('Bearer test-token', $request['headers']['Authorization']);
    }

    public function testNoAuthorizationHeaderWithoutToken(): void
    {
        $client = new FiQueLaClient('https://api.example.com', null, $this->transport);
        $this->transport->addResponse(new Response(200, [], '{"token":"t"}'));

        $client->login('user', 'pass');

        $request = $this->transport->getLastRequest();
        $this->assertArrayNotHasKey('Authorization', $request['headers']);
    }

    // -------------------------------------------------------
    // Server errors
    // -------------------------------------------------------

    public function testServerError(): void
    {
        $this->transport->addResponse(new Response(500, [], '{"error":"Internal server error"}'));

        $this->expectException(ServerException::class);
        $this->client->listFiles();
    }
}
