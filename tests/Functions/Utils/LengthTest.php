<?php

namespace Functions\Utils;

use FQL\Functions\Utils\Length;
use PHPUnit\Framework\TestCase;

class LengthTest extends TestCase
{

    public function testLength(): void
    {
        $length = new Length('name');

        $data = ['name' => 'John Doe'];
        $result = $length($data, []);
        $this->assertEquals(8, $result);

        $data = ['name' => ''];
        $result = $length($data, []);
        $this->assertEquals(0, $result);

        $length = new Length('surname');
        $data = ['surname' => null];
        $result = $length($data, []);
        $this->assertEquals(0, $result);
    }

    public function testLengthWithArray(): void
    {
        $length = new Length('tags');

        $data = ['tags' => ['tag1', 'tag2', 'tag3']];
        $result = $length($data, []);
        $this->assertEquals(3, $result);
    }

    public function testLengthWithAssociativeArray(): void
    {
        $length = new Length('tags');

        $data = ['tags' => ['tag1' => 'value1', 'tag2' => 'value2']];
        $result = $length($data, []);
        $this->assertEquals(2, $result);
    }

    public function testLengthWithEmptyArray(): void
    {
        $length = new Length('tags');

        $data = ['tags' => []];
        $result = $length($data, []);
        $this->assertEquals(0, $result);
    }

    public function testLengthWithNumberValue(): void
    {
        $length = new Length('age');

        $data = ['age' => 25];
        $result = $length($data, []);
        $this->assertEquals(2, $result);
    }
}
