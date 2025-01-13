<?php

namespace Enum;

use PHPUnit\Framework\TestCase;
use FQL\Enum\Type;

class TypeTest extends TestCase
{
    public function testCastValue(): void
    {
        $this->assertSame(1, Type::castValue(1, Type::INTEGER));
        $this->assertSame(1, Type::castValue("1", Type::INTEGER));
        $this->assertSame(1.0, Type::castValue(1, Type::FLOAT));
        $this->assertSame(1.0, Type::castValue("1", Type::FLOAT));
        $this->assertSame("1", Type::castValue(1, Type::STRING));
        $this->assertSame("1", Type::castValue("1", Type::STRING));
        $this->assertSame(true, Type::castValue(1, Type::BOOLEAN));
        $this->assertSame(true, Type::castValue("1", Type::BOOLEAN));
        $this->assertSame(false, Type::castValue(0, Type::BOOLEAN));
        $this->assertSame(false, Type::castValue("", Type::BOOLEAN));
        $this->assertSame(false, Type::castValue("0", Type::BOOLEAN));
        $this->assertSame(null, Type::castValue("", Type::NULL));
        $this->assertSame(null, Type::castValue(null, Type::NULL));

        $types = [
            "string",
            23,
            23.0,
            true,
            false,
            null,
        ];

        // Test scalar or null to array
        foreach ($types as $type) {
            $value = Type::castValue($type, Type::ARRAY);
            $this->assertEquals($type, $value[0]);
        }

        // Test scalar or null to object
        foreach ($types as $type) {
            $value = Type::castValue($type, Type::OBJECT);
            $this->assertEquals(null, $value);
        }

        // Test array to array is the same
        $castedTypes = Type::castValue($types, Type::ARRAY);
        foreach ($castedTypes as $index => $value) {
            $this->assertSame($value, $types[$index]);
        }
    }

    public function testMatchByValue(): void
    {
        $this->assertSame(Type::BOOLEAN, Type::matchByValue(true));
        $this->assertSame(Type::BOOLEAN, Type::matchByValue(false));
        $this->assertSame(Type::INTEGER, Type::matchByValue(1));
        $this->assertSame(Type::FLOAT, Type::matchByValue(1.0));
        $this->assertSame(Type::STRING, Type::matchByValue("1"));
        $this->assertSame(Type::ARRAY, Type::matchByValue([]));
        $this->assertSame(Type::OBJECT, Type::matchByValue(new \stdClass()));
        $this->assertSame(Type::NULL, Type::matchByValue(null));
    }

    public function testMatchByString(): void
    {
        $this->assertSame(null, Type::matchByString("NULL"));
        $this->assertSame(null, Type::matchByString("null"));
        $this->assertSame("NuLl", Type::matchByString("NuLl"));
        $this->assertSame("NULL ", Type::matchByString("NULL "));
        $this->assertSame(" NULL", Type::matchByString(" NULL"));
        $this->assertSame(" NULL ", Type::matchByString(" NULL "));
        $this->assertSame(true, Type::matchByString("TRUE"));
        $this->assertSame(true, Type::matchByString("true"));
        $this->assertSame("TrUe", Type::matchByString("TrUe"));
        $this->assertSame("TRUE ", Type::matchByString("TRUE "));
        $this->assertSame(" TRUE", Type::matchByString(" TRUE"));
        $this->assertSame(" TRUE ", Type::matchByString(" TRUE "));
        $this->assertSame(false, Type::matchByString("FALSE"));
        $this->assertSame(false, Type::matchByString("false"));
        $this->assertSame("FaLsE", Type::matchByString("FaLsE"));
        $this->assertSame("FALSE ", Type::matchByString("FALSE "));
        $this->assertSame(" FALSE", Type::matchByString(" FALSE"));
        $this->assertSame(" FALSE ", Type::matchByString(" FALSE "));
        $this->assertSame(1, Type::matchByString("1"));
        $this->assertSame(1.0, Type::matchByString("1.0"));
        $this->assertSame(1, Type::matchByString("1"));
        $this->assertSame(1.0, Type::matchByString("1.0"));
        $this->assertSame("string", Type::matchByString("string"));
        $this->assertSame(" string", Type::matchByString(" string"));
        $this->assertSame("string ", Type::matchByString("string "));
        $this->assertSame(" string ", Type::matchByString(" string "));
    }
}
