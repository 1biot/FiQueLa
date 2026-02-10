<?php

namespace Functions\Core;

use FQL\Functions\Core\BaseFunction;
use FQL\Functions\Core\BaseFunctionByReference;
use PHPUnit\Framework\TestCase;

class BaseFunctionTest extends TestCase
{
    public function testBaseFunctionGetNameAndFieldValue(): void
    {
        $function = new TestBaseFunction();

        $this->assertSame('TEST_BASE_FUNCTION', $function->getName());
        $this->assertSame('10', $function->exposeGetFieldValue('"10"', [], []));
        $this->assertSame(5, $function->exposeGetFieldValue('value', ['value' => 5], []));
        $this->assertSame('fallback', $function->exposeGetFieldValue('value', [], ['value' => 'fallback']));
        $this->assertNull($function->exposeGetFieldValue('missing', [], []));
    }

    public function testBaseFunctionByReferenceGetNameAndFieldValue(): void
    {
        $function = new TestBaseFunctionByReference();

        $this->assertSame('TEST_BASE_FUNCTION_BY_REFERENCE', $function->getName());
        $this->assertSame('10', $function->exposeGetFieldValue('"10"', [], []));
        $this->assertSame(1, $function->exposeGetFieldValue('value', ['value' => 1], []));
        $this->assertSame('value', $function->exposeGetFieldValue('value', [], ['value' => 'value']));
    }
}

final class TestBaseFunction extends BaseFunction
{
    public function __toString(): string
    {
        return $this->getName();
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        return $this->getFieldValue('value', $item, $resultItem);
    }

    public function exposeGetFieldValue(string $field, array $item, array $resultItem): mixed
    {
        return $this->getFieldValue($field, $item, $resultItem);
    }
}

final class TestBaseFunctionByReference extends BaseFunctionByReference
{
    public function __toString(): string
    {
        return $this->getName();
    }

    public function __invoke(array $item, array &$resultItem): mixed
    {
        $resultItem['value'] = $this->getFieldValue('value', $item, $resultItem);
        return null;
    }

    public function exposeGetFieldValue(string $field, array $item, array $resultItem): mixed
    {
        return $this->getFieldValue($field, $item, $resultItem);
    }
}
