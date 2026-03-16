<?php

namespace Client\Dto;

use FQL\Client\Dto\HistoryEntry;
use PHPUnit\Framework\TestCase;

class HistoryEntryTest extends TestCase
{
    public function testFromArray(): void
    {
        $entry = HistoryEntry::fromArray([
            'created_at' => '2023-10-01T12:00:00Z',
            'query' => 'select * from items',
            'runs' => 'SELECT * FROM [xml](file.xml).items',
        ]);

        $this->assertEquals('2023-10-01T12:00:00Z', $entry->createdAt);
        $this->assertEquals('select * from items', $entry->query);
        $this->assertEquals('SELECT * FROM [xml](file.xml).items', $entry->runs);
    }

    public function testFromArrayDefaults(): void
    {
        $entry = HistoryEntry::fromArray([]);

        $this->assertEquals('', $entry->createdAt);
        $this->assertEquals('', $entry->query);
        $this->assertEquals('', $entry->runs);
    }
}
