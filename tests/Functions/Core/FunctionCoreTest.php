<?php

namespace Functions\Core;

use FQL\Functions\Core\BaseFunctionByReference;
use FQL\Functions\Core\NoFieldFunction;
use PHPUnit\Framework\TestCase;

class FunctionCoreTest extends TestCase
{
    public function testBaseFunctionByReferenceNameAndFieldValue(): void
    {
        $function = new TestByReferenceFunction();
        $result = [];

        $function(['name' => 'Alpha'], $result);

        $this->assertSame('TEST_BY_REFERENCE_FUNCTION', $function->getName());
        $this->assertSame('Alpha', $result['value']);
        $this->assertSame('10', $function->extractValue('"10"', [], []));
        $this->assertSame('Beta', $function->extractValue('name', [], ['name' => 'Beta']));
    }

    public function testNoFieldFunctionName(): void
    {
        $function = new TestNoFieldFunction();

        $this->assertSame('TEST_NO_FIELD_FUNCTION', $function->getName());
        $this->assertSame('ok', $function());
    }
}

final class TestByReferenceFunction extends BaseFunctionByReference
{
    public function __toString(): string
    {
        return $this->getName();
    }

    public function extractValue(string $field, array $item, array $resultItem): mixed
    {
        return $this->getFieldValue($field, $item, $resultItem);
    }

    public function __invoke(array $item, array &$resultItem): mixed
    {
        $resultItem['value'] = $this->getFieldValue('name', $item, $resultItem);
        return null;
    }
}

final class TestNoFieldFunction extends NoFieldFunction
{
    public function __toString(): string
    {
        return $this->getName();
    }

    public function __invoke(): mixed
    {
        return 'ok';
    }
}
