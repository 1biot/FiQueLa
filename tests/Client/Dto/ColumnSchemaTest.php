<?php

namespace Client\Dto;

use FQL\Client\Dto\ColumnSchema;
use PHPUnit\Framework\TestCase;

class ColumnSchemaTest extends TestCase
{
    public function testFromArray(): void
    {
        $schema = ColumnSchema::fromArray([
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
        ]);

        $this->assertEquals('title', $schema->column);
        $this->assertEquals(['string' => 23, 'empty-string' => 12], $schema->types);
        $this->assertEquals(35, $schema->totalRows);
        $this->assertEquals(2, $schema->totalTypes);
        $this->assertEquals('string', $schema->dominant);
        $this->assertFalse($schema->suspicious);
        $this->assertEquals(0.95, $schema->confidence);
        $this->assertEquals(0.98, $schema->completeness);
        $this->assertFalse($schema->constant);
        $this->assertFalse($schema->isEnum);
        $this->assertTrue($schema->isUnique);
    }

    public function testFromArrayDefaults(): void
    {
        $schema = ColumnSchema::fromArray([]);

        $this->assertEquals('', $schema->column);
        $this->assertEquals([], $schema->types);
        $this->assertEquals(0, $schema->totalRows);
        $this->assertEquals(0, $schema->totalTypes);
        $this->assertNull($schema->dominant);
        $this->assertFalse($schema->suspicious);
        $this->assertEquals(0.0, $schema->confidence);
        $this->assertEquals(0.0, $schema->completeness);
        $this->assertFalse($schema->constant);
        $this->assertFalse($schema->isEnum);
        $this->assertFalse($schema->isUnique);
    }
}
