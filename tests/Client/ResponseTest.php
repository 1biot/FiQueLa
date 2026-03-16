<?php

namespace Client;

use FQL\Client\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], '{"key":"value"}');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['Content-Type' => 'application/json'], $response->getHeaders());
        $this->assertEquals('{"key":"value"}', $response->getBody());
    }

    public function testGetHeaderCaseInsensitive(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], '');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertEquals('application/json', $response->getHeader('CONTENT-TYPE'));
        $this->assertNull($response->getHeader('X-Missing'));
    }

    public function testJsonDecoding(): void
    {
        $response = new Response(200, [], '{"query":"SELECT *","data":[1,2,3]}');
        $json = $response->json();

        $this->assertEquals('SELECT *', $json['query']);
        $this->assertEquals([1, 2, 3], $json['data']);
    }

    public function testJsonDecodingInvalidJson(): void
    {
        $response = new Response(200, [], 'not json');
        $this->assertEquals([], $response->json());
    }

    public function testJsonDecodingNonArrayJson(): void
    {
        $response = new Response(200, [], '"just a string"');
        $this->assertEquals([], $response->json());
    }

    public function testIsSuccess(): void
    {
        $this->assertTrue((new Response(200, [], ''))->isSuccess());
        $this->assertTrue((new Response(201, [], ''))->isSuccess());
        $this->assertTrue((new Response(204, [], ''))->isSuccess());
        $this->assertFalse((new Response(400, [], ''))->isSuccess());
        $this->assertFalse((new Response(500, [], ''))->isSuccess());
    }

    public function testIsClientError(): void
    {
        $this->assertTrue((new Response(400, [], ''))->isClientError());
        $this->assertTrue((new Response(401, [], ''))->isClientError());
        $this->assertTrue((new Response(404, [], ''))->isClientError());
        $this->assertTrue((new Response(422, [], ''))->isClientError());
        $this->assertFalse((new Response(200, [], ''))->isClientError());
        $this->assertFalse((new Response(500, [], ''))->isClientError());
    }

    public function testIsServerError(): void
    {
        $this->assertTrue((new Response(500, [], ''))->isServerError());
        $this->assertTrue((new Response(502, [], ''))->isServerError());
        $this->assertFalse((new Response(200, [], ''))->isServerError());
        $this->assertFalse((new Response(404, [], ''))->isServerError());
    }
}
