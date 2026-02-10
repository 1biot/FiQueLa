<?php

namespace Functions\Core;

use FQL\Exception\InvalidArgumentException;
use FQL\Functions\Core\AggregateFunction;
use PHPUnit\Framework\TestCase;

class AggregateFunctionTest extends TestCase
{
    public function testGetNameAndFieldValue(): void
    {
        $function = new TestAggregateFunction();

        $this->assertSame('TEST_AGGREGATE_FUNCTION', $function->getName());
        $this->assertSame('value', $function->exposeGetFieldValue('value', ['value' => 'value'], true));
        $this->assertNull($function->exposeGetFieldValue('missing', ['value' => 'value'], false));
    }

    public function testGetFieldValueThrowsOnMissing(): void
    {
        $function = new TestAggregateFunction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "missing" not found');

        $function->exposeGetFieldValue('missing', ['value' => 'value'], true);
    }
}

final class TestAggregateFunction extends AggregateFunction
{
    public function __toString(): string
    {
        return $this->getName();
    }

    public function __invoke(array $items): mixed
    {
        return null;
    }

    public function initAccumulator(): mixed
    {
        return null;
    }

    public function accumulate(mixed $accumulator, array $item): mixed
    {
        return $accumulator;
    }

    public function finalize(mixed $accumulator): mixed
    {
        return $accumulator;
    }

    public function exposeGetFieldValue(string $field, array $item, bool $throwOnMissing): mixed
    {
        return $this->getFieldValue($field, $item, $throwOnMissing);
    }
}
