<?php

namespace Query;

use FQL\Enum\Format;
use FQL\Query\FileQuery;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class FileQueryExtraTest extends TestCase
{
    public function testFromStreamUsesProvideSource(): void
    {
        $stream = Json::string('{"id":1}');

        $fileQuery = FileQuery::fromStream($stream);

        $this->assertSame('[jsonFile](memory)', (string) $fileQuery);
    }

    public function testToStringOmitsDefaultEncodingAndDelimiter(): void
    {
        $fileQuery = new FileQuery('[csv](/tmp/file.csv, utf-8, ",")');

        $this->assertSame('[csv](/tmp/file.csv)', (string) $fileQuery);
    }

    public function testWithEncodingAndDelimiterPreservesQuery(): void
    {
        $fileQuery = new FileQuery('[csv](/tmp/file.csv, windows-1250, ";").items');

        $withEncoding = $fileQuery->withEncoding('utf-8');
        $withDelimiter = $fileQuery->withDelimiter(',');

        $this->assertSame('[csv](/tmp/file.csv, utf-8, ";").items', (string) $withEncoding);
        $this->assertSame('[csv](/tmp/file.csv, windows-1250).items', (string) $withDelimiter);
    }

    public function testWithFileAndExtension(): void
    {
        $fileQuery = new FileQuery('items');
        $withFile = $fileQuery->withFile('/tmp/data.json');
        $withExtension = $withFile->withExtension(Format::JSON);

        $this->assertSame('(/tmp/data.json).items', (string) $withFile);
        $this->assertSame('[jsonFile](/tmp/data.json).items', (string) $withExtension);
    }

    public function testWithQueryClearsQuery(): void
    {
        $fileQuery = new FileQuery('[csv](/tmp/file.csv).items');
        $cleared = $fileQuery->withQuery(null);

        $this->assertSame('[csv](/tmp/file.csv)', (string) $cleared);
    }

    public function testWithExtensionNullRemovesExtension(): void
    {
        $fileQuery = new FileQuery('[csv](/tmp/file.csv).items');
        $cleared = $fileQuery->withExtension(null);

        $this->assertSame('(/tmp/file.csv).items', (string) $cleared);
    }
}
