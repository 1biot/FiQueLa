<?php

namespace Cli\Command;

use FQL\Cli\Command\QueryCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class QueryCommandTest extends TestCase
{
    private string $tempFile;
    private string $tempHome;
    private string|false $originalHome;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/fql-command-' . uniqid() . '.csv';
        file_put_contents($this->tempFile, "id;name\n1;Alice\n2;Bob\n");

        $this->tempHome = sys_get_temp_dir() . '/fql-home-' . uniqid();
        mkdir($this->tempHome, 0700, true);
        mkdir($this->tempHome . '/.fql', 0700, true);

        $this->originalHome = getenv('HOME');
        putenv('HOME=' . $this->tempHome);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        $authFile = $this->tempHome . '/.fql/auth.json';
        $sessionFile = $this->tempHome . '/.fql/sessions.json';
        if (file_exists($authFile)) {
            unlink($authFile);
        }
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
        if (is_dir($this->tempHome . '/.fql')) {
            rmdir($this->tempHome . '/.fql');
        }
        if (is_dir($this->tempHome)) {
            rmdir($this->tempHome);
        }

        if ($this->originalHome !== false) {
            putenv('HOME=' . $this->originalHome);
        }
    }

    public function testRunNonInteractiveLocalMode(): void
    {
        $command = new QueryCommand();
        $tester = new CommandTester($command);

        $code = $tester->execute([
            'query' => 'SELECT id, name FROM *;',
            '--file' => $this->tempFile,
            '--file-type' => 'csv',
            '--file-delimiter' => ';',
        ]);

        $this->assertSame(0, $code);
        $output = trim($tester->getDisplay());
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
    }

    public function testRunNonInteractiveFailsOnMissingFile(): void
    {
        $command = new QueryCommand();
        $tester = new CommandTester($command);

        $code = $tester->execute([
            'query' => 'SELECT id FROM *;',
            '--file' => '/no/such/file.csv',
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('File not found', $tester->getDisplay());
    }

    public function testApiModeFailsWhenAuthPermissionsInvalidAndMissingCliCredentials(): void
    {
        $authFile = $this->tempHome . '/.fql/auth.json';
        file_put_contents($authFile, json_encode([
            ['name' => 'staging', 'url' => 'https://api.example.com', 'user' => 'u', 'secret' => 's'],
        ]));
        chmod($authFile, 0644);

        $command = new QueryCommand();
        $tester = new CommandTester($command);

        $code = $tester->execute([
            'query' => 'SELECT 1;',
            '--connect' => true,
        ]);

        $this->assertSame(1, $code);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('incorrect permissions', $display);
        $this->assertStringContainsString('must provide --user', $display);
    }
}
