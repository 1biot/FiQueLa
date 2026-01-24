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

    public function testGroupConcatDistinct(): void
    {
        $groupConcat = new GroupConcat('name', ',', true);
        $this->assertEquals(
            'Product A,Product B',
            $groupConcat([
                ['name' => 'Product A'],
                ['name' => 'Product A'],
                ['name' => 'Product B'],
                ['name' => 'Product B']
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

    public function testGroupConcatIncrementalMatchesInvoke(): void
    {
        $groupConcat = new GroupConcat('name');
        $items = [
            ['name' => 'Product A'],
            ['name' => null],
            ['name' => 'Product B'],
            ['name' => 'Product C'],
        ];

        $accumulator = $groupConcat->initAccumulator();
        foreach ($items as $item) {
            $accumulator = $groupConcat->accumulate($accumulator, $item);
        }

        $this->assertEquals($groupConcat($items), $groupConcat->finalize($accumulator));
    }

    public function testGroupConcatDistinctIncrementalMatchesInvoke(): void
    {
        $groupConcat = new GroupConcat('name', ',', true);
        $items = [
            ['name' => 'Product A'],
            ['name' => 'Product A'],
            ['name' => 'Product B'],
            ['name' => 'Product B'],
        ];

        $accumulator = $groupConcat->initAccumulator();
        foreach ($items as $item) {
            $accumulator = $groupConcat->accumulate($accumulator, $item);
        }

        $this->assertEquals($groupConcat($items), $groupConcat->finalize($accumulator));
    }
}
