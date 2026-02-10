<?php

namespace Functions\Utils;

use FQL\Functions\Utils\ColSplit;
use PHPUnit\Framework\TestCase;

class ColSplitTest extends TestCase
{
    public function testSplitDefaultFormat(): void
    {
        $split = new ColSplit('meta.tags');
        $resultItem = [];
        $result = $split(['meta' => ['tags' => ['a', 'b']]], $resultItem);

        $this->assertNull($result);
        $this->assertSame([
            'meta_tags_1' => 'a',
            'meta_tags_2' => 'b',
        ], $resultItem);
        $this->assertSame('COL_SPLIT(meta.tags)', (string) $split);
    }

    public function testSplitWithFormatAndKeyField(): void
    {
        $split = new ColSplit('items', 'item_%index', 'id');
        $resultItem = [];
        $split([
            'items' => [
                ['id' => 'first', 'value' => 1],
                ['id' => 'second', 'value' => 2],
            ],
        ], $resultItem);

        $this->assertSame([
            'item_first' => ['id' => 'first', 'value' => 1],
            'item_second' => ['id' => 'second', 'value' => 2],
        ], $resultItem);
        $this->assertSame('COL_SPLIT(items, "item_%index", "id")', (string) $split);
    }

    public function testSplitNonArrayValue(): void
    {
        $split = new ColSplit('items');
        $resultItem = ['keep' => 'value'];
        $result = $split(['items' => 'not-array'], $resultItem);

        $this->assertNull($result);
        $this->assertSame(['keep' => 'value'], $resultItem);
    }

    public function testSplitWithMissingKeyUsesIndex(): void
    {
        $split = new ColSplit('items', 'item_%index', 'id');
        $resultItem = [];
        $split([
            'items' => [
                ['value' => 1],
                ['value' => 2],
            ],
        ], $resultItem);

        $this->assertSame([
            'item_1' => ['value' => 1],
            'item_2' => ['value' => 2],
        ], $resultItem);
        $this->assertSame('COL_SPLIT(items, "item_%index", "id")', (string) $split);
    }

    public function testToStringWithNullFormatAndKeyField(): void
    {
        $split = new ColSplit('items', null, 'id');

        $this->assertSame('COL_SPLIT(items, null, "id")', (string) $split);
    }
}
