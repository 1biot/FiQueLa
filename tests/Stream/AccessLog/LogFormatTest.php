<?php

namespace Stream\AccessLog;

use FQL\Exception\InvalidFormatException;
use FQL\Stream\AccessLog\LogFormat;
use PHPUnit\Framework\TestCase;

class LogFormatTest extends TestCase
{
    public function testHasProfileReturnsTrue(): void
    {
        $this->assertTrue(LogFormat::hasProfile('nginx_combined'));
        $this->assertTrue(LogFormat::hasProfile('nginx_main'));
        $this->assertTrue(LogFormat::hasProfile('apache_combined'));
        $this->assertTrue(LogFormat::hasProfile('apache_common'));
    }

    public function testHasProfileReturnsFalse(): void
    {
        $this->assertFalse(LogFormat::hasProfile('nonexistent'));
    }

    public function testGetAvailableProfiles(): void
    {
        $profiles = LogFormat::getAvailableProfiles();
        $this->assertCount(4, $profiles);
        $this->assertContains('nginx_combined', $profiles);
        $this->assertContains('nginx_main', $profiles);
        $this->assertContains('apache_combined', $profiles);
        $this->assertContains('apache_common', $profiles);
    }

    public function testGetProfileRegexNginxCombined(): void
    {
        $regex = LogFormat::getProfileRegex('nginx_combined');
        $line = '192.168.1.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /apache_pb.gif HTTP/1.0" 200 2326 "http://www.example.com/start.html" "Mozilla/4.08"';
        $this->assertEquals(1, preg_match($regex, $line, $matches));
        $this->assertEquals('192.168.1.1', $matches['host']);
        $this->assertEquals('frank', $matches['user']);
        $this->assertEquals('200', $matches['status']);
    }

    public function testGetProfileRegexNginxMain(): void
    {
        $regex = LogFormat::getProfileRegex('nginx_main');
        $line = '192.168.1.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /path HTTP/1.0" 200 2326';
        $this->assertEquals(1, preg_match($regex, $line, $matches));
        $this->assertEquals('192.168.1.1', $matches['host']);
    }

    public function testGetProfileRegexApacheCombined(): void
    {
        $regex = LogFormat::getProfileRegex('apache_combined');
        $line = '127.0.0.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /index.html HTTP/1.0" 200 2326 "http://example.com" "Mozilla/5.0"';
        $this->assertEquals(1, preg_match($regex, $line, $matches));
        $this->assertEquals('127.0.0.1', $matches['host']);
        $this->assertEquals('-', $matches['logname']);
        $this->assertEquals('frank', $matches['user']);
    }

    public function testGetProfileRegexApacheCommon(): void
    {
        $regex = LogFormat::getProfileRegex('apache_common');
        $line = '127.0.0.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /index.html HTTP/1.0" 200 2326';
        $this->assertEquals(1, preg_match($regex, $line, $matches));
        $this->assertEquals('127.0.0.1', $matches['host']);
        $this->assertEquals('2326', $matches['responseBytes']);
    }

    public function testGetProfileRegexUnknownThrows(): void
    {
        $this->expectException(InvalidFormatException::class);
        LogFormat::getProfileRegex('nonexistent');
    }

    public function testLogFormatSimpleHost(): void
    {
        $result = LogFormat::logFormatToRegex('%h');
        $this->assertContains('host', $result['fields']);
        $this->assertEquals(1, preg_match($result['regex'], '192.168.1.1'));
    }

    public function testLogFormatLiteralPercent(): void
    {
        $result = LogFormat::logFormatToRegex('%%');
        $this->assertEquals(1, preg_match($result['regex'], '%'));
        $this->assertEquals(0, preg_match($result['regex'], 'x'));
    }

    public function testLogFormatMultiCharToken(): void
    {
        $result = LogFormat::logFormatToRegex('%>s');
        $this->assertContains('status', $result['fields']);
        $this->assertEquals(1, preg_match($result['regex'], '200'));
        $this->assertEquals('int', $result['types']['status']);
    }

    public function testLogFormatHeaderToken(): void
    {
        $result = LogFormat::logFormatToRegex('"%{Referer}i"');
        $this->assertContains('referer', $result['fields']);
        $this->assertEquals(1, preg_match($result['regex'], '"http://example.com"'));
    }

    public function testLogFormatHeaderNameNormalization(): void
    {
        $result = LogFormat::logFormatToRegex('%{X-Forwarded-For}i');
        $this->assertContains('x_forwarded_for', $result['fields']);
    }

    public function testLogFormatPortFormatToken(): void
    {
        $result = LogFormat::logFormatToRegex('%{canonical}p');
        $this->assertContains('port', $result['fields']);
        $this->assertEquals('int', $result['types']['port']);
    }

    public function testLogFormatQuotedContext(): void
    {
        $result = LogFormat::logFormatToRegex('"%r"');
        $this->assertEquals(1, preg_match($result['regex'], '"GET /path HTTP/1.1"'));
        // Should capture the full request including spaces
        preg_match($result['regex'], '"GET /path HTTP/1.1"', $matches);
        $this->assertEquals('GET /path HTTP/1.1', $matches['request']);
    }

    public function testLogFormatBracketContext(): void
    {
        $result = LogFormat::logFormatToRegex('[%t]');
        $this->assertEquals(1, preg_match($result['regex'], '[10/Oct/2000:13:55:36 -0700]'));
        preg_match($result['regex'], '[10/Oct/2000:13:55:36 -0700]', $matches);
        $this->assertEquals('10/Oct/2000:13:55:36 -0700', $matches['time']);
    }

    public function testLogFormatUnknownTokenThrows(): void
    {
        $this->expectException(InvalidFormatException::class);
        LogFormat::logFormatToRegex('%Z');
    }

    public function testLogFormatUnclosedBraceThrows(): void
    {
        $this->expectException(InvalidFormatException::class);
        LogFormat::logFormatToRegex('%{Foo');
    }

    public function testLogFormatFullNginxCombined(): void
    {
        $result = LogFormat::logFormatToRegex('%h - %u [%t] "%r" %>s %b "%{Referer}i" "%{User-Agent}i"');
        $line = '192.168.1.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /path HTTP/1.0" 200 2326 "http://example.com" "Mozilla/5.0"';
        $this->assertEquals(1, preg_match($result['regex'], $line, $matches));
        $this->assertEquals('192.168.1.1', $matches['host']);
        $this->assertEquals('frank', $matches['user']);
        $this->assertEquals('GET /path HTTP/1.0', $matches['request']);
        $this->assertEquals('200', $matches['status']);
        $this->assertEquals('2326', $matches['responseBytes']);
        $this->assertEquals('http://example.com', $matches['referer']);
        $this->assertEquals('Mozilla/5.0', $matches['user_agent']);
    }

    public function testLogFormatFullApacheCommon(): void
    {
        $result = LogFormat::logFormatToRegex('%h %l %u [%t] "%r" %>s %b');
        $line = '127.0.0.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /index.html HTTP/1.0" 200 2326';
        $this->assertEquals(1, preg_match($result['regex'], $line, $matches));
        $this->assertEquals('127.0.0.1', $matches['host']);
        $this->assertEquals('-', $matches['logname']);
        $this->assertEquals('frank', $matches['user']);
    }

    public function testNormalizeInt(): void
    {
        $fields = ['status'];
        $types = ['status' => 'int'];
        $matches = ['status' => '200'];
        $result = LogFormat::normalizeValues($matches, $fields, $types);
        $this->assertSame(200, $result['status']);
    }

    public function testNormalizeIntNullable(): void
    {
        $fields = ['responseBytes'];
        $types = ['responseBytes' => 'int_nullable'];

        $result = LogFormat::normalizeValues(['responseBytes' => '-'], $fields, $types);
        $this->assertNull($result['responseBytes']);

        $result = LogFormat::normalizeValues(['responseBytes' => '1024'], $fields, $types);
        $this->assertSame(1024, $result['responseBytes']);
    }

    public function testNormalizeFloat(): void
    {
        $fields = ['requestTime'];
        $types = ['requestTime' => 'float'];
        $result = LogFormat::normalizeValues(['requestTime' => '0.123'], $fields, $types);
        $this->assertSame(0.123, $result['requestTime']);
    }

    public function testNormalizeMicroseconds(): void
    {
        $fields = ['timeServeRequest'];
        $types = ['timeServeRequest' => 'microseconds'];
        $result = LogFormat::normalizeValues(['timeServeRequest' => '1500'], $fields, $types);
        $this->assertSame(1.5, $result['timeServeRequest']);
    }

    public function testNormalizeTime(): void
    {
        $fields = ['time'];
        $types = ['time' => 'time'];
        $result = LogFormat::normalizeValues(['time' => '10/Oct/2000:13:55:36 -0700'], $fields, $types);
        $this->assertEquals('2000-10-10 13:55:36', $result['time']);
    }

    public function testNormalizeTimeBadFormat(): void
    {
        $fields = ['time'];
        $types = ['time' => 'time'];
        $result = LogFormat::normalizeValues(['time' => 'not-a-date'], $fields, $types);
        $this->assertEquals('not-a-date', $result['time']);
    }

    public function testNormalizeRequestSplit(): void
    {
        $fields = ['request'];
        $types = ['request' => 'request'];
        $result = LogFormat::normalizeValues(['request' => 'GET /index.html HTTP/1.1'], $fields, $types);
        $this->assertEquals('GET /index.html HTTP/1.1', $result['request']);
        $this->assertEquals('GET', $result['method']);
        $this->assertEquals('/index.html', $result['path']);
        $this->assertEquals('HTTP/1.1', $result['protocol']);
    }

    public function testNormalizeRequestIncomplete(): void
    {
        $fields = ['request'];
        $types = ['request' => 'request'];
        $result = LogFormat::normalizeValues(['request' => 'GET /index.html'], $fields, $types);
        $this->assertEquals('GET', $result['method']);
        $this->assertEquals('/index.html', $result['path']);
        $this->assertNull($result['protocol']);
    }

    public function testNormalizeRequestDash(): void
    {
        $fields = ['request'];
        $types = ['request' => 'request'];
        $result = LogFormat::normalizeValues(['request' => '-'], $fields, $types);
        $this->assertEquals('-', $result['request']);
        $this->assertNull($result['method']);
        $this->assertNull($result['path']);
        $this->assertNull($result['protocol']);
    }

    public function testNormalizeStringNullable(): void
    {
        $fields = ['user'];
        $types = ['user' => 'string_nullable'];

        $result = LogFormat::normalizeValues(['user' => '-'], $fields, $types);
        $this->assertNull($result['user']);

        $result = LogFormat::normalizeValues(['user' => 'frank'], $fields, $types);
        $this->assertEquals('frank', $result['user']);
    }

    public function testNullRow(): void
    {
        $fields = ['host', 'user', 'request'];
        $types = ['host' => 'string', 'user' => 'string_nullable', 'request' => 'request'];
        $result = LogFormat::nullRow($fields, $types);

        $this->assertNull($result['host']);
        $this->assertNull($result['user']);
        $this->assertNull($result['request']);
        $this->assertNull($result['method']);
        $this->assertNull($result['path']);
        $this->assertNull($result['protocol']);
    }

    public function testDuplicateGroupNames(): void
    {
        $result = LogFormat::logFormatToRegex('%h %h');
        $this->assertContains('host', $result['fields']);
        $this->assertContains('host_2', $result['fields']);
        $this->assertEquals(1, preg_match($result['regex'], '192.168.1.1 10.0.0.1', $matches));
        $this->assertEquals('192.168.1.1', $matches['host']);
        $this->assertEquals('10.0.0.1', $matches['host_2']);
    }
}
