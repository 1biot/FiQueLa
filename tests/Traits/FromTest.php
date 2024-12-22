<?php

namespace UQL\Traits;

use PHPUnit\Framework\TestCase;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Stream\Json;

class FromTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/data/products.json'));
    }

    public function testFromWithInvalidPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Key 'invalid' not found.");

        $this->json->query()
            ->from('data.invalid.path')
            ->fetch();
    }

    public function testFrom(): void
    {
        $query = $this->json->query()
            ->from('data.products');

        $result = $query->fetchAll();
        $count = $query->count();

        self::assertSame(count(iterator_to_array($result)), $count);
    }
}
