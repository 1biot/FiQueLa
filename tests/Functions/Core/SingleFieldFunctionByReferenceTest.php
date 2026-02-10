<?php

namespace Functions\Core;

use FQL\Functions\Core\SingleFieldFunctionByReference;
use PHPUnit\Framework\TestCase;

class SingleFieldFunctionByReferenceTest extends TestCase
{
    public function testToStringUsesField(): void
    {
        $function = new TestSingleFieldByReference('name');

        $this->assertSame('TEST_SINGLE_FIELD_BY_REFERENCE(name)', (string) $function);
    }
}

final class TestSingleFieldByReference extends SingleFieldFunctionByReference
{
    public function __invoke(array $item, array &$resultItem): mixed
    {
        $resultItem['value'] = $item[$this->field] ?? null;
        return null;
    }
}
