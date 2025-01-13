<?php

namespace Traits;

use PHPUnit\Framework\TestCase;
use FQL\Query\Query;
use FQL\Query\TestProvider;

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
