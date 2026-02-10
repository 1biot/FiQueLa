<?php

namespace Results;

use FQL\Exception\InvalidArgumentException;
use FQL\Results\InMemory;
use PHPUnit\Framework\TestCase;

class ResultsProviderExtraTest extends TestCase
{
    private InMemory $proxy;

    protected function setUp(): void
    {
        $this->proxy = new InMemory([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
            ['id' => 3, 'name' => 'C'],
            ['id' => 4, 'name' => 'D'],
        ]);
    }

    public function testFetchNthEvenOdd(): void
    {
        $even = iterator_to_array($this->proxy->fetchNth('even'));
        $odd = iterator_to_array($this->proxy->fetchNth('odd'));

        $this->assertSame(['A', 'C'], array_column($even, 'name'));
        $this->assertSame(['B', 'D'], array_column($odd, 'name'));
    }

    public function testFetchNthRejectsInvalidParam(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid parameter: bad. Allowed values are an integer, 'even', or 'odd'.");

        iterator_to_array($this->proxy->fetchNth('bad'));
    }

    public function testFetchAllMapsToConstructorDto(): void
    {
        $results = iterator_to_array($this->proxy->fetchAll(DtoWithConstructor::class));

        $this->assertInstanceOf(DtoWithConstructor::class, $results[0]);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame('A', $results[0]->name);
    }

    public function testFetchAllMapsToPublicProperties(): void
    {
        $results = iterator_to_array($this->proxy->fetchAll(DtoWithPublicProps::class));

        $this->assertInstanceOf(DtoWithPublicProps::class, $results[1]);
        $this->assertSame(2, $results[1]->id);
        $this->assertSame('B', $results[1]->name);
    }
}

final class DtoWithConstructor
{
    public function __construct(public int $id, public string $name)
    {
    }
}

final class DtoWithPublicProps
{
    public int $id;
    public string $name;
}
