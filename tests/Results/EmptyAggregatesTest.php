<?php

namespace Results;

use FQL\Enum\Operator;
use FQL\Interface\Aggregable;
use FQL\Results\InMemory;
use FQL\Results\Stream;
use FQL\Stream\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * sum() / avg() / min() / max() of a result with no rows or no numeric value:
 * sum() is 0, the others are null.
 */
class EmptyAggregatesTest extends TestCase
{
    private const DATA = '{"data":[{"id":1,"price":"n/a","note":null},{"id":2,"price":"free","note":null}]}';

    /**
     * @return array<string, array{\Closure(): Aggregable, string}>
     */
    public static function emptyResults(): array
    {
        $stream = static fn (): \FQL\Interface\Query => Json::string(self::DATA)->query()->from('data');
        return [
            'in memory, no rows' => [static fn () => new InMemory([]), 'price'],
            'stream, no rows' => [
                static fn () => $stream()->where('id', Operator::GREATER_THAN, 10)->execute(Stream::class),
                'price',
            ],
            'in memory, no numeric value' => [
                static fn () => new InMemory([['price' => 'n/a'], ['price' => null], ['other' => 1]]),
                'price',
            ],
            'stream, no numeric value' => [static fn () => $stream()->execute(Stream::class), 'price'],
            'stream, only nulls' => [static fn () => $stream()->execute(Stream::class), 'note'],
            'stream, missing field' => [static fn () => $stream()->execute(Stream::class), 'missing'],
        ];
    }

    /**
     * @param \Closure(): Aggregable $results
     */
    #[DataProvider('emptyResults')]
    public function testAggregatesOfEmptySet(\Closure $results, string $key): void
    {
        $result = $results();
        $this->assertEquals(0, $result->sum($key));
        $this->assertNull($result->avg($key));
        $this->assertNull($result->min($key));
        $this->assertNull($result->max($key));

        // Stream caches the values; a second call must give the same answer.
        $this->assertNull($result->min($key));
        $this->assertNull($result->max($key));
        $this->assertNull($result->avg($key));
    }

    public function testMinAndMaxSkipValuesThatAreNotNumeric(): void
    {
        $result = new InMemory([['p' => 5], ['p' => null], ['p' => 'x'], ['q' => 1], ['p' => -3.5], ['p' => '7']]);

        $this->assertSame(-3.5, $result->min('p'));
        $this->assertSame(7.0, $result->max('p'));
    }

    public function testMaxOfNegativeNumbersIgnoresMissingValues(): void
    {
        $result = new InMemory([['p' => -5], ['q' => 1], ['p' => -2]]);

        $this->assertSame(-2.0, $result->max('p'));
        $this->assertSame(-5.0, $result->min('p'));
    }

    public function testAvgOfNumbersIsUnchanged(): void
    {
        $result = new InMemory([['p' => 100], ['p' => 200], ['p' => 400]]);

        $this->assertSame(233.33, $result->avg('p'));
        $this->assertSame(233.3, $result->avg('p', 1));
    }
}
