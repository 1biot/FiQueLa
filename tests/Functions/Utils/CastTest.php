<?php

namespace Functions\Utils;

use FQL\Enum\Type;
use FQL\Functions\Utils\Cast;
use PHPUnit\Framework\TestCase;

class CastTest extends TestCase
{
    public function testCast(): void
    {
        $cast = new Cast('price', Type::FLOAT);
        $this->assertSame(12.5, $cast(['price' => '12.5'], []));
        $this->assertSame(12.5, $cast(['price' => '12,5'], []));
        $this->assertSame('CAST(price AS DOUBLE)', (string) $cast);

        $cast = new Cast('integerValue', Type::INTEGER);
        $this->assertSame(12, $cast(['integerValue' => '12.5'], []));
        $this->assertSame(12, $cast(['integerValue' => '12'], []));
        $this->assertSame(0, $cast(['integerValue' => 'foo'], []));

        $cast = new Cast('floatValue', Type::FLOAT);
        $this->assertSame(12.5, $cast(['floatValue' => 12.5], []));

        $cast = new Cast('numberValue', Type::NUMBER);
        $this->assertSame(12, $cast(['numberValue' => '12'], []));
        $this->assertSame(12.5, $cast(['numberValue' => '12.5'], []));
        $this->assertSame(12.5, $cast(['numberValue' => '12,5'], []));
        $this->assertSame(7, $cast(['numberValue' => 7], []));
        $this->assertSame(7.5, $cast(['numberValue' => 7.5], []));
        $this->assertSame(0, $cast(['numberValue' => 'foo'], []));

        $cast = new Cast('stringValue', Type::STRING);
        $this->assertSame('7', $cast(['stringValue' => 7], []));
        $this->assertSame('["a","b"]', $cast(['stringValue' => ['a', 'b']], []));

        $cast = new Cast('arrayValue', Type::ARRAY);
        $this->assertSame(['a', 'b'], $cast(['arrayValue' => ['a', 'b']], []));
        $this->assertSame(['value'], $cast(['arrayValue' => 'value'], []));

        $objectValue = new \stdClass();
        $objectValue->id = 5;
        $cast = new Cast('objectValue', Type::OBJECT);
        $this->assertSame($objectValue, $cast(['objectValue' => $objectValue], []));
        $this->assertNull($cast(['objectValue' => 7], []));

        $cast = new Cast('nullValue', Type::NULL);
        $this->assertNull($cast(['nullValue' => 'anything'], []));

        $cast = new Cast('flag', Type::BOOLEAN);
        $this->assertTrue($cast(['flag' => 'true'], []));
        $this->assertFalse($cast(['flag' => '0'], []));
        $this->assertTrue($cast(['flag' => '1'], []));
        $this->assertFalse($cast(['flag' => 'false'], []));
    }

    public function testCastWithMissingValue(): void
    {
        $cast = new Cast('missing', Type::INTEGER);
        $this->assertNull($cast(['value' => 10], []));
    }
}
