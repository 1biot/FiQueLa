<?php

namespace Stream;

use FQL\Exception\FileNotFoundException;
use FQL\Exception\NotImplementedException;
use FQL\Stream\AccessLog;
use PHPUnit\Framework\TestCase;

class AccessLogTest extends TestCase
{
    private string $nginxCombinedFile;
    private string $apacheCommonFile;
    private string $malformedFile;

    protected function setUp(): void
    {
        $this->nginxCombinedFile = realpath(__DIR__ . '/../../examples/data/access-nginx-combined.log');
        $this->apacheCommonFile = realpath(__DIR__ . '/../../examples/data/access-apache-common.log');
        $this->malformedFile = realpath(__DIR__ . '/../../examples/data/access-malformed.log');
    }

    public function testOpen(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $this->assertInstanceOf(AccessLog::class, $log);
    }

    public function testOpenFileNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);
        AccessLog::open('/path/to/file/not/existed.log');
    }

    public function testStringThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        AccessLog::string('some data');
    }

    public function testNginxCombinedProfile(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));

        $this->assertCount(5, $rows);

        $first = $rows[0];
        $this->assertEquals('192.168.1.1', $first['host']);
        $this->assertEquals('frank', $first['user']);
        $this->assertEquals('2000-10-10 13:55:36', $first['time']);
        $this->assertEquals('GET /apache_pb.gif HTTP/1.0', $first['request']);
        $this->assertSame(200, $first['status']);
        $this->assertSame(2326, $first['responseBytes']);
        $this->assertEquals('http://www.example.com/start.html', $first['referer']);
        $this->assertStringContainsString('Mozilla', $first['user_agent']);
        $this->assertEquals('GET', $first['method']);
        $this->assertEquals('/apache_pb.gif', $first['path']);
        $this->assertEquals('HTTP/1.0', $first['protocol']);
    }

    public function testNginxMainProfile(): void
    {
        // nginx_main expects fewer fields than nginx_combined lines have,
        // so we test with a dedicated fixture line via custom pattern
        $log = AccessLog::open($this->nginxCombinedFile);
        $log->setFormat('nginx_main');
        $rows = iterator_to_array($log->getStreamGenerator(null));

        // nginx_combined lines have extra referer/user-agent fields,
        // so they won't match nginx_main format (anchored regex)
        $this->assertCount(5, $rows);
        foreach ($rows as $row) {
            $this->assertEquals('pattern mismatch', $row['_error']);
        }
    }

    public function testApacheCommonProfile(): void
    {
        $log = AccessLog::open($this->apacheCommonFile);
        $log->setFormat('apache_common');
        $rows = iterator_to_array($log->getStreamGenerator(null));

        $this->assertCount(4, $rows);
        $first = $rows[0];
        $this->assertEquals('127.0.0.1', $first['host']);
        $this->assertNull($first['logname']); // '-' normalized to null
        $this->assertEquals('frank', $first['user']);
        $this->assertSame(200, $first['status']);
        $this->assertSame(2326, $first['responseBytes']);
    }

    public function testApacheCombinedProfile(): void
    {
        // apache_combined has same structure as nginx_combined (with logname field)
        // nginx_combined lines use '-' for logname implicitly via the literal '-'
        // The apache_combined format: %h %l %u [%t] "%r" %>s %b "%{Referer}i" "%{User-Agent}i"
        // nginx_combined lines: host - user [...] — the '-' matches as logname
        $log = AccessLog::open($this->nginxCombinedFile);
        $log->setFormat('apache_combined');
        $rows = iterator_to_array($log->getStreamGenerator(null));
        $this->assertCount(5, $rows);
        // First line: 192.168.1.1 - frank [...] — '-' is logname
        $this->assertEquals('192.168.1.1', $rows[0]['host']);
        $this->assertNull($rows[0]['logname']); // '-' normalized to null
        $this->assertEquals('frank', $rows[0]['user']);
    }

    public function testMalformedLineYieldsError(): void
    {
        $log = AccessLog::open($this->malformedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));

        // File has: valid, invalid, valid, (empty), invalid, valid = 5 non-empty lines
        $this->assertCount(5, $rows);

        // Second row is invalid
        $this->assertEquals('pattern mismatch', $rows[1]['_error']);
        $this->assertEquals('this is not a valid log line', $rows[1]['_raw']);
        $this->assertNull($rows[1]['host']);

        // Fourth row (index 3) is invalid
        $this->assertEquals('pattern mismatch', $rows[3]['_error']);
    }

    public function testEmptyLinesSkipped(): void
    {
        $log = AccessLog::open($this->malformedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));

        // The empty line should be skipped entirely
        foreach ($rows as $row) {
            $this->assertNotEquals('', trim($row['_raw']));
        }
    }

    public function testStatusIsInt(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));
        $this->assertIsInt($rows[0]['status']);
    }

    public function testResponseBytesNullForDash(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));

        // Line 4 (index 3): "204 -" — responseBytes should be null
        $this->assertNull($rows[3]['responseBytes']);
    }

    public function testTimeNormalized(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));
        $this->assertEquals('2000-10-10 13:55:36', $rows[0]['time']);
    }

    public function testRequestSplit(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));
        $this->assertEquals('GET', $rows[0]['method']);
        $this->assertEquals('/apache_pb.gif', $rows[0]['path']);
        $this->assertEquals('HTTP/1.0', $rows[0]['protocol']);
    }

    public function testUserNullForDash(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));
        // Line 2 (index 1) has user=-
        $this->assertNull($rows[1]['user']);
    }

    public function testSetFormat(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $log->setFormat('nginx_main');
        $this->assertEquals('nginx_main', $log->getFormat());
    }

    public function testSetPattern(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $log->setPattern('%h %u');
        $this->assertEquals('%h %u', $log->getPattern());
    }

    public function testCustomPattern(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        // Custom pattern matching just host and status
        $log->setPattern('%h - %u [%t] "%r" %>s %b "%{Referer}i" "%{User-Agent}i"');
        $rows = iterator_to_array($log->getStreamGenerator(null));
        $this->assertCount(5, $rows);
        $this->assertEquals('192.168.1.1', $rows[0]['host']);
    }

    public function testProvideSourceDefault(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $source = $log->provideSource();
        $this->assertEquals('log(access-nginx-combined.log)', $source);
    }

    public function testProvideSourceWithFormat(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $log->setFormat('apache_combined');
        $source = $log->provideSource();
        $this->assertEquals('log(access-nginx-combined.log, "apache_combined")', $source);
    }

    public function testRawFieldPresent(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));
        foreach ($rows as $row) {
            $this->assertArrayHasKey('_raw', $row);
            $this->assertNotEmpty($row['_raw']);
        }
    }

    public function testErrorFieldNullOnSuccess(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $rows = iterator_to_array($log->getStreamGenerator(null));
        foreach ($rows as $row) {
            $this->assertNull($row['_error']);
        }
    }

    public function testGetStream(): void
    {
        $log = AccessLog::open($this->nginxCombinedFile);
        $stream = $log->getStream(null);
        $this->assertInstanceOf(\ArrayIterator::class, $stream);
        $this->assertCount(5, $stream);
    }
}
