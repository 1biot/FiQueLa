<?php

namespace Utils;

use FQL\Exception\InvalidFormatException;
use FQL\Query\FileQuery;
use FQL\Utils\FileQueryPathValidator;
use PHPUnit\Framework\TestCase;

class FileQueryPathValidatorTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/fiquela-path-validator-' . uniqid();
        mkdir($this->basePath . '/input', 0775, true);
        file_put_contents($this->basePath . '/input/data.json', '{"a":1}');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);
    }

    public function testValidateExistingFileResolvesCanonicalPath(): void
    {
        $fileQuery = new FileQuery('json(input/data.json)');

        $validated = FileQueryPathValidator::validate($fileQuery, $this->basePath);

        $this->assertSame(realpath($this->basePath . '/input/data.json'), $validated->file);
    }

    public function testValidateTargetFileAllowsNonExistingPathWhenMustExistFalse(): void
    {
        $fileQuery = new FileQuery('csv(exports/out.csv)');

        $validated = FileQueryPathValidator::validate($fileQuery, $this->basePath, false);

        $expected = rtrim((string) realpath($this->basePath), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'exports'
            . DIRECTORY_SEPARATOR
            . 'out.csv';
        $this->assertSame($expected, $validated->file);
    }

    public function testValidateRejectsTraversalOutsideBasePath(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid path of file');

        $fileQuery = new FileQuery('csv(../escape.csv)');
        FileQueryPathValidator::validate($fileQuery, $this->basePath, false);
    }

    public function testValidateThrowsForInvalidBasePath(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid path of file');

        $fileQuery = new FileQuery('json(input/data.json)');
        FileQueryPathValidator::validate($fileQuery, $this->basePath . '/missing-base');
    }

    public function testNormalizePathSupportsWindowsLikePaths(): void
    {
        $method = new \ReflectionMethod(FileQueryPathValidator::class, 'normalizePath');

        /** @var string $normalized */
        $normalized = $method->invoke(null, 'c:\\tmp\\folder\\..\\output\\file.csv');

        $this->assertSame('c:' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . 'file.csv', $normalized);
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
