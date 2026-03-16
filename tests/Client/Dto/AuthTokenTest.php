<?php

namespace Client\Dto;

use FQL\Client\Dto\AuthToken;
use PHPUnit\Framework\TestCase;

class AuthTokenTest extends TestCase
{
    public function testFromArrayWithToken(): void
    {
        $token = AuthToken::fromArray(['token' => 'abc123']);
        $this->assertEquals('abc123', $token->token);
    }

    public function testFromArrayWithRevoked(): void
    {
        $token = AuthToken::fromArray(['revoked' => 'xyz789']);
        $this->assertEquals('xyz789', $token->token);
    }

    public function testFromArrayEmpty(): void
    {
        $token = AuthToken::fromArray([]);
        $this->assertEquals('', $token->token);
    }

    public function testDecodePayload(): void
    {
        // Create a simple JWT-like token with a valid base64-encoded payload
        $header = base64_encode('{"alg":"HS256","typ":"JWT"}');
        $payload = base64_encode(json_encode([
            'sub' => 'api-token',
            'username' => 'testuser',
            'exp' => time() + 3600,
        ]));
        $signature = base64_encode('signature');
        $jwt = "$header.$payload.$signature";

        $token = new AuthToken($jwt);
        $decoded = $token->decodePayload();

        $this->assertEquals('api-token', $decoded['sub']);
        $this->assertEquals('testuser', $decoded['username']);
    }

    public function testDecodePayloadInvalidToken(): void
    {
        $token = new AuthToken('invalid');
        $this->assertEquals([], $token->decodePayload());
    }

    public function testIsExpired(): void
    {
        $header = base64_encode('{"alg":"HS256"}');
        $signature = base64_encode('sig');

        // Not expired
        $payload = base64_encode(json_encode(['exp' => time() + 3600]));
        $notExpired = new AuthToken("$header.$payload.$signature");
        $this->assertFalse($notExpired->isExpired());

        // Expired
        $payload = base64_encode(json_encode(['exp' => time() - 3600]));
        $expired = new AuthToken("$header.$payload.$signature");
        $this->assertTrue($expired->isExpired());
    }

    public function testIsExpiredWithoutExpClaim(): void
    {
        $header = base64_encode('{"alg":"HS256"}');
        $payload = base64_encode(json_encode(['sub' => 'test']));
        $signature = base64_encode('sig');

        $token = new AuthToken("$header.$payload.$signature");
        $this->assertTrue($token->isExpired());
    }

    public function testGetExpiresAt(): void
    {
        $header = base64_encode('{"alg":"HS256"}');
        $exp = time() + 3600;
        $payload = base64_encode(json_encode(['exp' => $exp]));
        $signature = base64_encode('sig');

        $token = new AuthToken("$header.$payload.$signature");
        $this->assertEquals($exp, $token->getExpiresAt());
    }

    public function testGetExpiresAtNull(): void
    {
        $token = new AuthToken('invalid');
        $this->assertNull($token->getExpiresAt());
    }
}
