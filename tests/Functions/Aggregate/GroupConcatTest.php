<?php

namespace Functions\Aggregate;

use PHPUnit\Framework\TestCase;
use FQL\Functions\Aggregate\GroupConcat;

class GroupConcatTest extends TestCase
{
    public function testGroupConcat(): void
    {
        $groupConcat = new GroupConcat('name');
        $this->assertEquals(
            'Product A,Product B,Product C',
            $groupConcat(
                [
                    ['name' => 'Product A'],
                    ['name' => 'Product B'],
                    ['name' => 'Product C']
                ]
            )
        );
    }

    public function testGroupConcatWithEmptyArray(): void
    {
        $groupConcat = new GroupConcat('name');
        $this->assertEquals(
            '',
            $groupConcat([])
        );
    }

    public function testGroupConcatWithNumbers(): void
    {
        $groupConcat = new GroupConcat('numericPriceString');
        $this->assertEquals(
            '100,200,300,400,500',
            $groupConcat([
                ['numericPriceString' => 100],
                ['numericPriceString' => 200],
                ['numericPriceString' => 300],
                ['numericPriceString' => 400],
                ['numericPriceString' => 500]
            ])
        );
    }

    public function testGroupConcatWithUndefinedField(): void
    {
        $groupConcat = new GroupConcat('fieldNotExists');
        $this->assertEquals(
            '',
            $groupConcat([
                ['numericPriceString' => '100'],
                ['numericPriceString' => '200'],
                ['numericPriceString' => '300'],
                ['numericPriceString' => '400'],
                ['numericPriceString' => '500'],
                ['numericPriceString' => '600']
            ])
        );
    }
}
