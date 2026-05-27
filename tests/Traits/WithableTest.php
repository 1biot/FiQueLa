<?php

namespace Traits;

use FQL\Exception\AliasException;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the {@see \FQL\Traits\Withable} trait via the public
 * {@see \FQL\Query\Query} API — sanity-checking the registry semantics in
 * isolation from the SQL parser.
 */
class WithableTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/data/products.json'));
    }

    public function testRegistersAndRetrievesCte(): void
    {
        $cte = $this->json->query()->select('id')->from('data.products');
        $query = $this->json->query()->with('alpha', $cte);

        $this->assertTrue($query->hasCte('alpha'));
        $this->assertSame($cte, $query->getCte('alpha'));
        $this->assertSame(['alpha' => $cte], $query->getCtes());
    }

    public function testMissingCteLookupReturnsNull(): void
    {
        $query = $this->json->query();

        $this->assertFalse($query->hasCte('nope'));
        $this->assertNull($query->getCte('nope'));
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->expectException(AliasException::class);
        $this->expectExceptionMessage('CTE name cannot be empty');
        $this->json->query()->with('', $this->json->query());
    }

    public function testDuplicateNameIsRejected(): void
    {
        $cte = $this->json->query();
        $query = $this->json->query()->with('a', $cte);

        $this->expectException(AliasException::class);
        $this->expectExceptionMessage('CTE "a" is already registered');
        $query->with('a', $cte);
    }
}
