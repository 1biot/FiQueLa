<?php

namespace Cli\Query;

use FQL\Cli\Query\LocalQueryExecutor;
use PHPUnit\Framework\TestCase;

class LocalQueryExecutorTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/fql-local-executor-' . uniqid() . '.csv';
        file_put_contents($this->tempFile, "id;name\n1;Alice\n2;Bob\n3;Carol\n4;David\n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testExecuteWithPagination(): void
    {
        $executor = new LocalQueryExecutor($this->tempFile, 'csv', ';', 'utf-8');

        $result = $executor->execute('SELECT id, name FROM *', 1, 2);

        $this->assertEquals(['id', 'name'], $result->headers);
        $this->assertEquals(4, $result->totalCount);
        $this->assertCount(2, $result->data);
        $this->assertTrue($result->hasMorePages);
    }

    public function testExecuteAll(): void
    {
        $executor = new LocalQueryExecutor($this->tempFile, 'csv', ';', 'utf-8');

        $result = $executor->executeAll('SELECT id, name FROM *');

        $this->assertCount(4, $result->data);
        $this->assertEquals(4, $result->totalCount);
        $this->assertFalse($result->isEmpty());
    }

    public function testGettersAndModeName(): void
    {
        $executor = new LocalQueryExecutor($this->tempFile, 'csv', ';', 'utf-8');

        $this->assertEquals('LOCAL', $executor->getModeName());
        $this->assertEquals($this->tempFile, $executor->getFile());
        $this->assertEquals('utf-8', $executor->getEncoding());
        $this->assertEquals(';', $executor->getDelimiter());
    }

    public function testHighlightQueryOnSuccess(): void
    {
        $executor = new LocalQueryExecutor($this->tempFile, 'csv', ';', 'utf-8');

        $highlighted = $executor->highlightQuery('SELECT id, name FROM *');

        $this->assertIsString($highlighted);
        $this->assertNotSame('', $highlighted);
    }

    public function testHighlightQueryOnFailureReturnsOriginal(): void
    {
        $executor = new LocalQueryExecutor('/nonexistent/file.csv', 'csv', ';', 'utf-8');

        $query = 'SELECT * FROM *';
        $this->assertEquals($query, $executor->highlightQuery($query));
    }

    public function testExecuteWithoutFileUsingFqlProvider(): void
    {
        $executor = new LocalQueryExecutor(null, null, ';', 'utf-8');
        $query = 'SELECT id, name FROM [csv](' . $this->tempFile . ', utf-8, ";").*';

        $result = $executor->executeAll($query);

        $this->assertCount(4, $result->data);
        $this->assertEquals(['id', 'name'], $result->headers);
    }
}
