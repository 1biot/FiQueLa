<?php

namespace Utils;

use FQL\Utils\InMemoryHashmap;
use PHPUnit\Framework\TestCase;

class InMemoryHashmapTest extends TestCase
{
    private InMemoryHashmap $hashmap;

    protected function setUp(): void
    {
        $this->hashmap = new InMemoryHashmap();
    }

    // ── set / has / get ──────────────────────────────────

    public function testHasReturnsFalseOnEmptyHashmap(): void
    {
        $this->assertFalse($this->hashmap->has('nonexistent'));
        $this->assertFalse($this->hashmap->has(42));
    }

    public function testHasReturnsTrueAfterSet(): void
    {
        $this->hashmap->set('ean123', ['EAN' => 'ean123', 'PRICE' => 100]);
        $this->assertTrue($this->hashmap->has('ean123'));
    }

    public function testGetReturnsEmptyArrayForMissingKey(): void
    {
        $this->assertSame([], $this->hashmap->get('missing'));
        $this->assertSame([], $this->hashmap->get(99));
    }

    public function testGetReturnsSingleRow(): void
    {
        $row = ['EAN' => 'ean123', 'PRICE' => 100];
        $this->hashmap->set('ean123', $row);

        $result = $this->hashmap->get('ean123');
        $this->assertCount(1, $result);
        $this->assertSame($row, $result[0]);
    }

    public function testSetAcceptsIntegerKey(): void
    {
        $row = ['ID' => 42, 'NAME' => 'Product'];
        $this->hashmap->set(42, $row);

        $this->assertTrue($this->hashmap->has(42));
        $this->assertSame($row, $this->hashmap->get(42)[0]);
    }

    // ── multiple rows per key ────────────────────────────

    public function testSetAppendsMultipleRowsUnderSameKey(): void
    {
        $row1 = ['EAN' => 'ean123', 'PRICE' => 100];
        $row2 = ['EAN' => 'ean123', 'PRICE' => 200];
        $row3 = ['EAN' => 'ean123', 'PRICE' => 300];

        $this->hashmap->set('ean123', $row1);
        $this->hashmap->set('ean123', $row2);
        $this->hashmap->set('ean123', $row3);

        $result = $this->hashmap->get('ean123');
        $this->assertCount(3, $result);
        $this->assertSame($row1, $result[0]);
        $this->assertSame($row2, $result[1]);
        $this->assertSame($row3, $result[2]);
    }

    public function testMultipleKeysAreIsolated(): void
    {
        $rowA = ['EAN' => 'aaa', 'PRICE' => 10];
        $rowB = ['EAN' => 'bbb', 'PRICE' => 20];

        $this->hashmap->set('aaa', $rowA);
        $this->hashmap->set('bbb', $rowB);

        $this->assertCount(1, $this->hashmap->get('aaa'));
        $this->assertCount(1, $this->hashmap->get('bbb'));
        $this->assertSame($rowA, $this->hashmap->get('aaa')[0]);
        $this->assertSame($rowB, $this->hashmap->get('bbb')[0]);
    }

    // ── getStructure ─────────────────────────────────────

    public function testGetStructureReturnsEmptyArrayWhenEmpty(): void
    {
        $this->assertSame([], $this->hashmap->getStructure());
    }

    public function testGetStructureReturnsColumnNamesOfFirstRow(): void
    {
        $this->hashmap->set('ean123', ['EAN' => 'ean123', 'PRICE' => 100, 'NAME' => 'Product']);

        $structure = $this->hashmap->getStructure();
        $this->assertSame(['EAN', 'PRICE', 'NAME'], $structure);
    }

    public function testGetStructureIsConsistentAfterMultipleSets(): void
    {
        $this->hashmap->set('aaa', ['EAN' => 'aaa', 'PRICE' => 10]);
        $this->hashmap->set('bbb', ['EAN' => 'bbb', 'PRICE' => 20]);

        // structure should always reflect first inserted row
        $this->assertSame(['EAN', 'PRICE'], $this->hashmap->getStructure());
    }

    // ── clear ────────────────────────────────────────────

    public function testClearEmptiesAllData(): void
    {
        $this->hashmap->set('aaa', ['EAN' => 'aaa', 'PRICE' => 10]);
        $this->hashmap->set('bbb', ['EAN' => 'bbb', 'PRICE' => 20]);

        $this->hashmap->clear();

        $this->assertFalse($this->hashmap->has('aaa'));
        $this->assertFalse($this->hashmap->has('bbb'));
        $this->assertSame([], $this->hashmap->get('aaa'));
        $this->assertSame([], $this->hashmap->getStructure());
    }

    public function testClearAllowsReuse(): void
    {
        $this->hashmap->set('aaa', ['EAN' => 'aaa', 'PRICE' => 10]);
        $this->hashmap->clear();

        $newRow = ['EAN' => 'zzz', 'PRICE' => 999];
        $this->hashmap->set('zzz', $newRow);

        $this->assertTrue($this->hashmap->has('zzz'));
        $this->assertFalse($this->hashmap->has('aaa'));
        $this->assertSame($newRow, $this->hashmap->get('zzz')[0]);
    }

    // ── edge cases ───────────────────────────────────────

    public function testStringZeroKey(): void
    {
        $row = ['ID' => '0', 'NAME' => 'Zero'];
        $this->hashmap->set('0', $row);

        $this->assertTrue($this->hashmap->has('0'));
        $this->assertSame($row, $this->hashmap->get('0')[0]);
    }

    public function testIntegerZeroKey(): void
    {
        $row = ['ID' => 0, 'NAME' => 'Zero'];
        $this->hashmap->set(0, $row);

        $this->assertTrue($this->hashmap->has(0));
        $this->assertSame($row, $this->hashmap->get(0)[0]);
    }

    public function testRowWithNullValues(): void
    {
        $row = ['EAN' => 'ean123', 'PRICE' => null, 'NAME' => null];
        $this->hashmap->set('ean123', $row);

        $result = $this->hashmap->get('ean123');
        $this->assertSame($row, $result[0]);
    }

    public function testEmptyRowCanBeStored(): void
    {
        $this->hashmap->set('empty', []);
        $this->assertTrue($this->hashmap->has('empty'));
        $this->assertSame([[]], $this->hashmap->get('empty'));
    }

    public function testLargeNumberOfRows(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->hashmap->set('key', ['ID' => $i, 'VALUE' => "value_$i"]);
        }

        $result = $this->hashmap->get('key');
        $this->assertCount(1000, $result);
        $this->assertSame(0, $result[0]['ID']);
        $this->assertSame(999, $result[999]['ID']);
    }

    public function testLargeNumberOfKeys(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->hashmap->set("key_$i", ['ID' => $i]);
        }

        for ($i = 0; $i < 1000; $i++) {
            $this->assertTrue($this->hashmap->has("key_$i"));
            $this->assertSame($i, $this->hashmap->get("key_$i")[0]['ID']);
        }
    }
}
