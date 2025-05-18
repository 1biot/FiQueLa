<?php

namespace Functions\Utils;

use FQL\Functions\Utils\ArrayFilter;
use PHPUnit\Framework\TestCase;

class ArrayFilterTest extends TestCase
{
    public function testArrayFilter(): void
    {
        $arrayFilter = new ArrayFilter('field');
        $result = $arrayFilter(['field' => [1, 2, 3, null, 4]], []);
        $this->assertEquals([1, 2, 3, 4], $result);

        $arrayFilterEmpty = new ArrayFilter('field');
        $resultEmpty = $arrayFilterEmpty(['field' => []], []);
        $this->assertEquals([], $resultEmpty);

        $arrayFilterString = (string) $arrayFilter;
        $this->assertEquals('ARRAY_FILTER(field)', $arrayFilterString);
    }

    public function testArrayFilterWithNullValue(): void
    {
        $arrayFilter = new ArrayFilter('field');
        $result = $arrayFilter([], []);
        $this->assertEquals(null, $result);

        $arrayFilterString = (string) $arrayFilter;
        $this->assertEquals('ARRAY_FILTER(field)', $arrayFilterString);
    }
}
