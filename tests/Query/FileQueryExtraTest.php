<?php

namespace Query;

use FQL\Query\FileQuery;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class FileQueryExtraTest extends TestCase
{
    public function testFromStreamUsesProvideSource(): void
    {
        $stream = Json::string('{"id":1}');

        $fileQuery = FileQuery::fromStream($stream);

        $this->assertSame('json(memory)', (string) $fileQuery);
    }

    public function testToStringOmitsDefaultEncodingAndDelimiter(): void
    {
        $fileQuery = new FileQuery('csv(/tmp/file.csv, "utf-8", ",")');

        $this->assertSame('csv(/tmp/file.csv)', (string) $fileQuery);
    }

    public function testWithEncodingAndDelimiterPreservesQuery(): void
    {
        $fileQuery = new FileQuery('csv(/tmp/file.csv, "windows-1250", ";").items');

        $withEncoding = $fileQuery->withEncoding('utf-8');
        $withDelimiter = $fileQuery->withDelimiter(',');

        $this->assertSame('csv(/tmp/file.csv, "utf-8", ";").items', (string) $withEncoding);
        $this->assertSame('csv(/tmp/file.csv, "windows-1250").items', (string) $withDelimiter);
    }

    public function testWithFileAndFormat(): void
    {
        $fileQuery = new FileQuery('json(/tmp/data.json).items');
        $withFile = $fileQuery->withFile('/tmp/other.json');
        $withFormat = $fileQuery->withFormat('xml');

        $this->assertSame('json(/tmp/other.json).items', (string) $withFile);
        $this->assertSame('xml(/tmp/data.json).items', (string) $withFormat);
    }

    public function testWithQueryClearsQuery(): void
    {
        $fileQuery = new FileQuery('csv(/tmp/file.csv).items');
        $cleared = $fileQuery->withQuery(null);

        $this->assertSame('csv(/tmp/file.csv)', (string) $cleared);
    }

    public function testWithFormatNullRemovesFormat(): void
    {
        $fileQuery = new FileQuery('csv(/tmp/file.csv).items');
        $cleared = $fileQuery->withFormat(null);

        $this->assertSame('items', (string) $cleared);
    }

    public function testWithParam(): void
    {
        $fileQuery = new FileQuery('csv(data.csv)');

        $updated = $fileQuery->withParam('encoding', 'windows-1250');
        $this->assertSame('csv(data.csv, "windows-1250")', (string) $updated);

        $updated2 = $fileQuery->withParam('delimiter', ';');
        $this->assertSame('csv(data.csv, "utf-8", ";")', (string) $updated2);
    }
}
