<?php

namespace Functions\Utils;

use FQL\Functions\Utils\ArraySearch;
use PHPUnit\Framework\TestCase;

class ArraySearchTest extends TestCase
{
    public function testArraySearch(): void
    {
        $arraySearch = new ArraySearch('field', 'value');

        $result = $arraySearch(['field' => [1, 2, 3, null, 4, 'value']], []);
        $this->assertEquals(5, $result);

        $result = $arraySearch(['field' => [1, 2, 3, null, 4]], []);
        $this->assertFalse($result);
    }

    public function testArraySearchNoArray(): void
    {
        $arraySearch = new ArraySearch('field', 'value');
        $result = $arraySearch(['field' => 'value'], []);
        $this->assertNull($result);

        $arraySearch = new ArraySearch('field[]', 'value');
        $result = $arraySearch(['field' => 'value'], []);
        $this->assertEquals(0, $result);
    }

    public function testToString(): void
    {
        $arrayFilter = new ArraySearch('field', 'value');
        $arraySearchString = (string) $arrayFilter;
        $this->assertEquals('ARRAY_SEARCH(field, "value")', $arraySearchString);
    }
}
