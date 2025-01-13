<?php

namespace Traits;

use PHPUnit\Framework\TestCase;
use FQL\Query\TestProvider;

class LimitTest extends TestCase
{
    /** @var TestProvider $query */
    private TestProvider $query;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->query = new TestProvider();
    }

    public function testLimit(): void
    {
        $this->query->limit(2);
        [$limit, $offset] = $this->query->getLimitAndOffset();
        $this->assertEquals(2, $limit);
        $this->assertNull($offset);

        $this->query->limit(10, 50);
        [$limit, $offset] = $this->query->getLimitAndOffset();
        $this->assertEquals(10, $limit);
        $this->assertEquals(50, $offset);

        $this->query->limit(200)->offset(1000);
        [$limit, $offset] = $this->query->getLimitAndOffset();
        $this->assertEquals(200, $limit);
        $this->assertEquals(1000, $offset);

        $this->query->page(8, 20);
        [$limit, $offset] = $this->query->getLimitAndOffset();
        $this->assertEquals(20, $limit);
        $this->assertEquals(140, $offset);
    }
}
