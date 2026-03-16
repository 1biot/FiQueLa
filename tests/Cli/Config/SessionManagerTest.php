<?php

namespace Cli\Config;

use FQL\Client\Dto\AuthToken;
use FQL\Cli\Config\SessionManager;
use PHPUnit\Framework\TestCase;

class SessionManagerTest extends TestCase
{
    private string $tempDir;
    private SessionManager $manager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/fql-session-test-' . uniqid();
        mkdir($this->tempDir, 0700, true);
        $this->manager = new SessionManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testGetSessionsFile(): void
    {
        $this->assertEquals($this->tempDir . '/sessions.json', $this->manager->getSessionsFile());
    }

    public function testValidatePermissionsWhenFileDoesNotExist(): void
    {
        // Should return true when file doesn't exist yet
        $this->assertTrue($this->manager->validatePermissions());
    }

    public function testValidatePermissionsCorrect(): void
    {
        $file = $this->tempDir . '/sessions.json';
        file_put_contents($file, '{}');
        chmod($file, 0600);

        $this->assertTrue($this->manager->validatePermissions());
    }

    public function testValidatePermissionsIncorrect(): void
    {
        $file = $this->tempDir . '/sessions.json';
        file_put_contents($file, '{}');
        chmod($file, 0644);

        $this->assertFalse($this->manager->validatePermissions());
    }

    public function testSaveAndGetToken(): void
    {
        // Create a JWT-like token with future expiration
        $header = base64_encode('{"alg":"HS256"}');
        $payload = base64_encode(json_encode(['exp' => time() + 3600]));
        $signature = base64_encode('sig');
        $jwt = "$header.$payload.$signature";

        $token = new AuthToken($jwt);
        $this->manager->saveToken('https://api.example.com', $token);

        // Verify file permissions
        $perms = fileperms($this->tempDir . '/sessions.json') & 0777;
        $this->assertEquals(0600, $perms);

        // Retrieve token
        $retrieved = $this->manager->getToken('https://api.example.com');
        $this->assertNotNull($retrieved);
        $this->assertEquals($jwt, $retrieved->token);
    }

    public function testGetTokenReturnsNullWhenNotStored(): void
    {
        $this->assertNull($this->manager->getToken('https://api.example.com'));
    }

    public function testGetTokenReturnsNullWhenExpired(): void
    {
        // Create token with past expiration
        $header = base64_encode('{"alg":"HS256"}');
        $payload = base64_encode(json_encode(['exp' => time() - 3600]));
        $signature = base64_encode('sig');
        $jwt = "$header.$payload.$signature";

        $token = new AuthToken($jwt);
        $this->manager->saveToken('https://api.example.com', $token);

        // Should return null because expired
        $this->assertNull($this->manager->getToken('https://api.example.com'));
    }

    public function testRemoveToken(): void
    {
        $header = base64_encode('{"alg":"HS256"}');
        $payload = base64_encode(json_encode(['exp' => time() + 3600]));
        $signature = base64_encode('sig');
        $jwt = "$header.$payload.$signature";

        $token = new AuthToken($jwt);
        $this->manager->saveToken('https://api.example.com', $token);
        $this->assertNotNull($this->manager->getToken('https://api.example.com'));

        $this->manager->removeToken('https://api.example.com');
        $this->assertNull($this->manager->getToken('https://api.example.com'));
    }

    public function testTrailingSlashNormalization(): void
    {
        $header = base64_encode('{"alg":"HS256"}');
        $payload = base64_encode(json_encode(['exp' => time() + 3600]));
        $signature = base64_encode('sig');
        $jwt = "$header.$payload.$signature";

        $token = new AuthToken($jwt);
        $this->manager->saveToken('https://api.example.com/', $token);

        // Should find the token without trailing slash too
        $retrieved = $this->manager->getToken('https://api.example.com');
        $this->assertNotNull($retrieved);
    }

    public function testMultipleServers(): void
    {
        $header = base64_encode('{"alg":"HS256"}');
        $signature = base64_encode('sig');

        $payload1 = base64_encode(json_encode(['exp' => time() + 3600]));
        $jwt1 = "$header.$payload1.$signature";
        $this->manager->saveToken('https://api1.example.com', new AuthToken($jwt1));

        $payload2 = base64_encode(json_encode(['exp' => time() + 7200]));
        $jwt2 = "$header.$payload2.$signature";
        $this->manager->saveToken('https://api2.example.com', new AuthToken($jwt2));

        $token1 = $this->manager->getToken('https://api1.example.com');
        $token2 = $this->manager->getToken('https://api2.example.com');

        $this->assertNotNull($token1);
        $this->assertNotNull($token2);
        $this->assertEquals($jwt1, $token1->token);
        $this->assertEquals($jwt2, $token2->token);
    }
}
