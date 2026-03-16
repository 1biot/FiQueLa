<?php

namespace Cli\Config;

use FQL\Cli\Config\ServerConfig;
use PHPUnit\Framework\TestCase;

class ServerConfigTest extends TestCase
{
    public function testFromArray(): void
    {
        $config = ServerConfig::fromArray([
            'name' => 'production',
            'url' => 'https://api.example.com/',
            'user' => 'admin',
            'secret' => 'SuperSecret123!',
        ]);

        $this->assertEquals('production', $config->name);
        $this->assertEquals('https://api.example.com', $config->url); // trailing slash removed
        $this->assertEquals('admin', $config->user);
        $this->assertEquals('SuperSecret123!', $config->secret);
    }

    public function testFromArrayDefaults(): void
    {
        $config = ServerConfig::fromArray([]);

        $this->assertEquals('', $config->name);
        $this->assertEquals('', $config->url);
        $this->assertEquals('', $config->user);
        $this->assertEquals('', $config->secret);
    }

    public function testToArray(): void
    {
        $config = new ServerConfig('local', 'http://localhost:6917', 'user', 'pass');
        $array = $config->toArray();

        $this->assertEquals([
            'name' => 'local',
            'url' => 'http://localhost:6917',
            'user' => 'user',
            'secret' => 'pass',
        ], $array);
    }

    public function testIsValid(): void
    {
        $valid = new ServerConfig('prod', 'https://api.example.com', 'admin', 'pass');
        $this->assertTrue($valid->isValid());

        $invalidName = new ServerConfig('', 'https://api.example.com', 'admin', 'pass');
        $this->assertFalse($invalidName->isValid());

        $invalidUrl = new ServerConfig('prod', '', 'admin', 'pass');
        $this->assertFalse($invalidUrl->isValid());

        $invalidUser = new ServerConfig('prod', 'https://api.example.com', '', 'pass');
        $this->assertFalse($invalidUser->isValid());

        $invalidSecret = new ServerConfig('prod', 'https://api.example.com', 'admin', '');
        $this->assertFalse($invalidSecret->isValid());
    }
}
