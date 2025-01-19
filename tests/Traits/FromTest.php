<?php

namespace Traits;

use FQL\Interfaces\Query;
use FQL\Query\TestProvider;
use PHPUnit\Framework\TestCase;

class FromTest extends TestCase
{
    /** @var TestProvider $query */
    private TestProvider $query;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->query = new TestProvider();
    }

    public function testFrom(): void
    {
        $this->query->from('data.products');
        $this->assertEquals('data.products', $this->query->getFromSource());

        $this->query->from('*');
        $this->assertEquals(Query::FROM_ALL, $this->query->getFromSource());
    }
}
