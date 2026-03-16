<?php

namespace Cli\Interactive;

use FQL\Cli\Interactive\HistoryManager;
use PHPUnit\Framework\TestCase;

class HistoryManagerTest extends TestCase
{
    private string $tempFile;
    private HistoryManager $manager;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/fql-history-test-' . uniqid();
        $this->manager = new HistoryManager($this->tempFile, 5);
    }

    protected function tearDown(): void
    {
        if (function_exists('readline_clear_history')) {
            readline_clear_history();
        }

        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testGetHistoryFile(): void
    {
        $this->assertEquals($this->tempFile, $this->manager->getHistoryFile());
    }

    public function testReplaceWithApiHistory(): void
    {
        $queries = [
            'SELECT * FROM items',
            'SELECT title FROM items WHERE price > 100',
            'SELECT COUNT(*) FROM items',
        ];

        $this->manager->replaceWithApiHistory($queries);

        $this->assertFileExists($this->tempFile);
        $content = (string) file_get_contents($this->tempFile);
        $decoded = $this->decodeReadlineHistory($content);

        $this->assertStringContainsString('_HiStOrY_V2_', $decoded);
        $this->assertStringContainsString('SELECT * FROM items', $decoded);
        $this->assertStringContainsString('SELECT title FROM items WHERE price > 100', $decoded);
    }

    public function testReplaceWithEmptyApiHistory(): void
    {
        $this->manager->replaceWithApiHistory([]);

        $this->assertFileExists($this->tempFile);
        $content = (string) file_get_contents($this->tempFile);
        $this->assertStringStartsWith("_HiStOrY_V2_\n", $content);
    }

    public function testSaveAddsHistoryEntry(): void
    {
        if (!function_exists('readline_clear_history')) {
            $this->markTestSkipped('readline not available');
        }

        readline_clear_history();
        $this->manager->save('SELECT * FROM test');

        $this->assertFileExists($this->tempFile);
        $content = (string) file_get_contents($this->tempFile);
        $decoded = $this->decodeReadlineHistory($content);
        $this->assertStringContainsString('SELECT * FROM test', $decoded);
    }

    public function testSaveDeduplicatesLastEntry(): void
    {
        if (!function_exists('readline_clear_history')) {
            $this->markTestSkipped('readline not available');
        }

        readline_clear_history();
        $this->manager->save('SELECT * FROM test');
        $this->manager->save('SELECT * FROM test');

        $content = (string) file_get_contents($this->tempFile);
        $decoded = $this->decodeReadlineHistory($content);
        $this->assertEquals(1, substr_count($decoded, 'SELECT * FROM test'));
    }

    public function testSaveTruncatesHistoryToLimit(): void
    {
        if (!function_exists('readline_clear_history')) {
            $this->markTestSkipped('readline not available');
        }

        readline_clear_history();

        for ($i = 1; $i <= 10; $i++) {
            $this->manager->save('SELECT ' . $i);
        }

        $content = (string) file_get_contents($this->tempFile);
        $decoded = $this->decodeReadlineHistory($content);

        // manager max entries is 5
        $this->assertStringContainsString('SELECT 10', $decoded);
        $this->assertStringContainsString('SELECT 6', $decoded);
        $this->assertDoesNotMatchRegularExpression('/SELECT 1\b/', $decoded);
    }

    private function decodeReadlineHistory(string $escapedString): string
    {
        return (string) preg_replace_callback('/\\\\([0-7]{1,3})/', function ($matches) {
            return chr((int) octdec($matches[1]));
        }, $escapedString);
    }
}
