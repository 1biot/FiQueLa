<?php

namespace Stream;

use FQL\Stream\Dir;
use PHPUnit\Framework\TestCase;

class DirTest extends TestCase
{
    private string $tempDir;
    private string $readableFile;
    private string $unreadableFile;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/fql-dir-test-' . uniqid();
        mkdir($this->tempDir, 0700, true);

        mkdir($this->tempDir . '/subdir', 0700, true);

        $this->readableFile = $this->tempDir . '/readable.txt';
        file_put_contents($this->readableFile, 'hello');

        $this->unreadableFile = $this->tempDir . '/private.txt';
        file_put_contents($this->unreadableFile, 'secret');
        @chmod($this->unreadableFile, 0000);
    }

    protected function tearDown(): void
    {
        @chmod($this->unreadableFile, 0600);

        if (file_exists($this->readableFile)) {
            unlink($this->readableFile);
        }
        if (file_exists($this->unreadableFile)) {
            unlink($this->unreadableFile);
        }
        if (is_dir($this->tempDir . '/subdir')) {
            rmdir($this->tempDir . '/subdir');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testDirectoryRowsDoNotTriggerMimeWarnings(): void
    {
        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        });

        try {
            $dir = Dir::open($this->tempDir);
            $rows = iterator_to_array($dir->getStream(null));
        } finally {
            restore_error_handler();
        }

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey('mime_type', $row);
            $this->assertArrayHasKey('hash_md5', $row);

            if ($row['is_dir'] === true) {
                $this->assertNull($row['mime_type']);
                $this->assertNull($row['hash_md5']);
            }
        }

        $mimeWarnings = array_filter($warnings, static fn(string $w): bool => str_contains($w, 'mime_content_type'));
        $this->assertCount(0, $mimeWarnings, 'No mime_content_type warnings should be emitted.');
    }
}
