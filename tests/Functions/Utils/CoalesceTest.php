<?php

namespace Functions\Utils;

use PHPUnit\Framework\TestCase;
use FQL\Functions\Utils\Coalesce;

class CoalesceTest extends TestCase
{
    public function testCeil(): void
    {
        $coalesce = new Coalesce('actionPrice', 'price', 'standardPrice');
        $this->assertEquals(
            80,
            $coalesce([
                'actionPrice' => 80,
                'price' => 100,
                'standardPrice' => 120,
            ], [])
        );
    }

    public function testCeilWithNull(): void
    {
        $coalesce = new Coalesce('actionPrice', 'price', 'standardPrice');
        $this->assertEquals(
            100,
            $coalesce([
                'actionPrice' => null,
                'price' => 100,
                'standardPrice' => 120,
            ], [])
        );
    }
}
