<?php

namespace FQL\Tests\Results;

use FQL\Enum\Sort;
use FQL\Results\BoundedSortHeap;
use PHPUnit\Framework\TestCase;

class BoundedSortHeapTest extends TestCase
{
    public function testKeepsTopNSmallestAscending(): void
    {
        // score => id : 5=>1, 1=>2, 3=>3, 2=>4, 4=>5
        $heap = new BoundedSortHeap([Sort::ASC], 3);
        foreach ([[5, 1], [1, 2], [3, 3], [2, 4], [4, 5]] as [$score, $id]) {
            $heap->offer([$score], ['id' => $id]);
        }

        // Three smallest scores (1, 2, 3) in ascending order => ids 2, 4, 3.
        $this->assertSame(
            [['id' => 2], ['id' => 4], ['id' => 3]],
            $heap->sorted()
        );
    }

    public function testKeepsTopNLargestDescending(): void
    {
        $heap = new BoundedSortHeap([Sort::DESC], 3);
        foreach ([[5, 1], [1, 2], [3, 3], [2, 4], [4, 5]] as [$score, $id]) {
            $heap->offer([$score], ['id' => $id]);
        }

        // Three largest scores (5, 4, 3) in descending order => ids 1, 5, 3.
        $this->assertSame(
            [['id' => 1], ['id' => 5], ['id' => 3]],
            $heap->sorted()
        );
    }

    public function testMultiKeyOrdering(): void
    {
        // ORDER BY group ASC, score DESC
        $heap = new BoundedSortHeap([Sort::ASC, Sort::DESC], 4);
        $rows = [
            ['keys' => [1, 10], 'id' => 'a'],
            ['keys' => [2, 50], 'id' => 'b'],
            ['keys' => [1, 30], 'id' => 'c'],
            ['keys' => [2, 20], 'id' => 'd'],
            ['keys' => [1, 20], 'id' => 'e'],
        ];
        foreach ($rows as $row) {
            $heap->offer($row['keys'], ['id' => $row['id']]);
        }

        // group 1 (score desc): c(30), e(20), a(10); group 2 (score desc): b(50), d(20)
        // Top 4 keeps the four that sort first: c, e, a, b
        $this->assertSame(
            [['id' => 'c'], ['id' => 'e'], ['id' => 'a'], ['id' => 'b']],
            $heap->sorted()
        );
    }

    public function testStableTieBreakKeepsInsertionOrder(): void
    {
        // All keys equal: the earliest-inserted rows must survive, in order.
        $heap = new BoundedSortHeap([Sort::ASC], 3);
        foreach (range(1, 5) as $id) {
            $heap->offer([0], ['id' => $id]);
        }

        $this->assertSame(
            [['id' => 1], ['id' => 2], ['id' => 3]],
            $heap->sorted()
        );
    }

    public function testCapacityLargerThanInputReturnsEverythingSorted(): void
    {
        $heap = new BoundedSortHeap([Sort::ASC], 100);
        foreach ([[3, 1], [1, 2], [2, 3]] as [$score, $id]) {
            $heap->offer([$score], ['id' => $id]);
        }

        $this->assertSame(
            [['id' => 2], ['id' => 3], ['id' => 1]],
            $heap->sorted()
        );
    }

    public function testZeroCapacityRetainsNothing(): void
    {
        $heap = new BoundedSortHeap([Sort::ASC], 0);
        $heap->offer([1], ['id' => 1]);
        $heap->offer([2], ['id' => 2]);

        $this->assertSame([], $heap->sorted());
    }

    public function testEmptyInputReturnsEmpty(): void
    {
        $heap = new BoundedSortHeap([Sort::ASC], 5);

        $this->assertSame([], $heap->sorted());
    }

    public function testSupportsMixedScalarKeyTypes(): void
    {
        $heap = new BoundedSortHeap([Sort::ASC], 3);
        foreach ([['banana', 1], ['apple', 2], ['cherry', 3], ['avocado', 4]] as [$name, $id]) {
            $heap->offer([$name], ['id' => $id]);
        }

        // Alphabetical: apple, avocado, banana => ids 2, 4, 1
        $this->assertSame(
            [['id' => 2], ['id' => 4], ['id' => 1]],
            $heap->sorted()
        );
    }

    /**
     * Cross-checks the heap against a stable usort + slice reference across
     * randomized inputs, directions, and capacities. PHP's usort is stable, so
     * ties keep insertion order — exactly the heap's tie-break contract.
     *
     * @dataProvider randomizedScenarios
     * @param array<int, Sort> $directions
     */
    public function testMatchesStableSortAndSliceReference(array $directions, int $capacity, int $seed): void
    {
        mt_srand($seed);

        /** @var list<array{keys: array<int, int>, item: array{id: int}}> $rows */
        $rows = [];
        $count = mt_rand(0, 60);
        for ($id = 0; $id < $count; $id++) {
            $keys = [];
            foreach ($directions as $_) {
                $keys[] = mt_rand(0, 5); // small range => lots of ties
            }
            $rows[] = ['keys' => $keys, 'item' => ['id' => $id]];
        }

        $heap = new BoundedSortHeap($directions, $capacity);
        foreach ($rows as $row) {
            $heap->offer($row['keys'], $row['item']);
        }

        $expected = $this->referenceTopN($rows, $directions, $capacity);

        $this->assertSame($expected, $heap->sorted());
    }

    /**
     * @return array<string, array{0: array<int, Sort>, 1: int, 2: int}>
     */
    public static function randomizedScenarios(): array
    {
        $scenarios = [];
        foreach ([[Sort::ASC], [Sort::DESC], [Sort::ASC, Sort::DESC], [Sort::DESC, Sort::ASC]] as $d => $directions) {
            foreach ([0, 1, 5, 10, 1000] as $capacity) {
                foreach ([1, 42, 7777] as $seed) {
                    $label = sprintf('dirs#%d cap=%d seed=%d', $d, $capacity, $seed);
                    $scenarios[$label] = [$directions, $capacity, $seed];
                }
            }
        }

        return $scenarios;
    }

    /**
     * Stable reference: full sort by the same key/direction rules, then take the
     * first $capacity rows.
     *
     * @param list<array{keys: array<int, int>, item: array{id: int}}> $rows
     * @param array<int, Sort> $directions
     * @return list<array{id: int}>
     */
    private function referenceTopN(array $rows, array $directions, int $capacity): array
    {
        usort($rows, static function (array $a, array $b) use ($directions): int {
            foreach ($directions as $i => $direction) {
                $cmp = $direction === Sort::ASC
                    ? ($a['keys'][$i] <=> $b['keys'][$i])
                    : ($b['keys'][$i] <=> $a['keys'][$i]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0; // stable: keep insertion order on full tie
        });

        return array_map(
            static fn (array $row): array => $row['item'],
            array_slice($rows, 0, $capacity)
        );
    }
}
