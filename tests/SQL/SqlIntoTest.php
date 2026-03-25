<?php

namespace SQL;

use FQL\Exception\InvalidFormatException;
use FQL\Query\TestProvider;
use FQL\Sql\Sql;
use PHPUnit\Framework\TestCase;

class SqlIntoTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/fiquela-into-' . uniqid();
        mkdir($this->basePath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);
    }

    public function testIntoClauseIsParsedAndStored(): void
    {
        $sql = new Sql('SELECT id INTO csv(output.csv)', $this->basePath);

        $query = $sql->parseWithQuery(new TestProvider());

        $this->assertTrue($query->hasInto());
        $this->assertNotNull($query->getInto());
        $this->assertSame(
            realpath($this->basePath) . '/output.csv',
            $query->getInto()?->file
        );
    }

    public function testIntoClauseRejectsPathTraversal(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid path of file');

        $sql = new Sql('SELECT id INTO csv(../escape.csv)', $this->basePath);
        $sql->parseWithQuery(new TestProvider());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
