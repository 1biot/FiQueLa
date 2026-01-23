<?php

namespace Stream;

use FQL\Exception\InvalidArgumentException;
use FQL\Interface\Stream as StreamInterface;
use FQL\Stream\ArrayStreamProvider;
use PHPUnit\Framework\TestCase;

class ArrayStreamProviderTest extends TestCase
{
    public function testGetStreamReturnsListItems(): void
    {
        $provider = new TestArrayStreamProvider(new \ArrayIterator([
            'items' => [1, 2],
        ]));

        $stream = $provider->getStream('items');
        $items = iterator_to_array($stream);

        $this->assertSame([
            ['items' => 1],
            ['items' => 2],
        ], $items);
    }

    public function testGetStreamReturnsNestedList(): void
    {
        $provider = new TestArrayStreamProvider(new \ArrayIterator([
            'items' => [
                ['id' => 1, 'name' => 'A'],
                ['id' => 2, 'name' => 'B'],
            ],
        ]));

        $stream = $provider->getStream('items');
        $items = iterator_to_array($stream);

        $this->assertSame([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ], $items);
    }

    public function testGetStreamThrowsOnMissingKey(): void
    {
        $provider = new TestArrayStreamProvider(new \ArrayIterator([
            'items' => [1, 2],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Key 'missing' not found.");

        $provider->getStream('missing');
    }
}

final class TestArrayStreamProvider extends ArrayStreamProvider
{
    public static function open(string $path): StreamInterface
    {
        return new self(new \ArrayIterator([]));
    }

    public static function string(string $data): StreamInterface
    {
        return new self(new \ArrayIterator([]));
    }

    public function provideSource(): string
    {
        return 'array';
    }
}
