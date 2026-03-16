<?php

namespace Client\Dto;

use FQL\Client\Dto\FileSchema;
use FQL\Client\Dto\ColumnSchema;
use PHPUnit\Framework\TestCase;

class FileSchemaTest extends TestCase
{
    public function testFromArray(): void
    {
        $schema = FileSchema::fromArray([
            'uuid' => '12345678-1234-5678-1234-123456789012',
            'name' => 'example.xml',
            'encoding' => 'utf-8',
            'type' => 'xml',
            'size' => 123456,
            'delimiter' => null,
            'query' => 'channel.items',
            'count' => 100,
            'columns' => [
                [
                    'column' => 'title',
                    'types' => ['string' => 23, 'empty-string' => 12],
                    'totalRows' => 35,
                    'totalTypes' => 2,
                    'dominant' => 'string',
                    'suspicious' => false,
                    'confidence' => 0.95,
                    'completeness' => 0.98,
                    'constant' => false,
                    'isEnum' => false,
                    'isUnique' => true,
                ],
            ],
        ]);

        $this->assertEquals('12345678-1234-5678-1234-123456789012', $schema->uuid);
        $this->assertEquals('example.xml', $schema->name);
        $this->assertEquals('utf-8', $schema->encoding);
        $this->assertEquals('xml', $schema->type);
        $this->assertEquals(123456, $schema->size);
        $this->assertNull($schema->delimiter);
        $this->assertEquals('channel.items', $schema->query);
        $this->assertEquals(100, $schema->count);
        $this->assertCount(1, $schema->columns);
        $this->assertInstanceOf(ColumnSchema::class, $schema->columns[0]);
        $this->assertEquals('title', $schema->columns[0]->column);
    }

    public function testFromArrayDefaults(): void
    {
        $schema = FileSchema::fromArray([]);

        $this->assertEquals('', $schema->uuid);
        $this->assertEquals('', $schema->name);
        $this->assertNull($schema->encoding);
        $this->assertEquals('', $schema->type);
        $this->assertEquals(0, $schema->size);
        $this->assertNull($schema->delimiter);
        $this->assertNull($schema->query);
        $this->assertEquals(0, $schema->count);
        $this->assertEmpty($schema->columns);
    }
}
