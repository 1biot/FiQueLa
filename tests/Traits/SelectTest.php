<?php

namespace Traits;

use FQL\Enum;
use FQL\Exception;
use FQL\Functions;
use FQL\Interface\Query;
use FQL\Query\TestProvider;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the fluent Select trait after the Expression-First refactor:
 *  - plain `select('name')` stores a ColumnReferenceNode in `expression`.
 *  - scalar helpers (`round`, `lower`, `concat`, ...) store a FunctionCallNode
 *    with the corresponding name.
 *  - aggregate helpers (`sum`, `avg`, `count`, ...) populate the `aggregate`
 *    slot with `{class, expression, options}` (no more wrapper instance).
 *  - `SELECT *` / `foo.*` keep expression/aggregate null so Stream handles them
 *    through wildcard expansion.
 */
class SelectTest extends TestCase
{
    private TestProvider $query;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->query = new TestProvider();
    }

    public function testSimpleSelect(): void
    {
        $this->query->select('id, name, price', 'brand.name', 'category.name', 'stock, purchasePrice');
        $this->assertCount(7, $this->query->getSelectedFields());

        foreach ($this->query->getSelectedFields() as $field => $data) {
            $this->assertEquals($field, $data['originField']);
            $this->assertFalse($data['alias']);
            $this->assertInstanceOf(ColumnReferenceNode::class, $data['expression']);
            $this->assertNull($data['aggregate']);
        }
    }

    public function testMultipleSelect(): void
    {
        $this->query->select('id')
            ->select('name')
            ->select('stock, purchasePrice')
            ->select('price')
            ->select('brand.name', 'category.name');
        $this->assertCount(7, $this->query->getSelectedFields());

        foreach ($this->query->getSelectedFields() as $field => $data) {
            $this->assertEquals($field, $data['originField']);
            $this->assertFalse($data['alias']);
            $this->assertInstanceOf(ColumnReferenceNode::class, $data['expression']);
        }
    }

    public function testAliasField(): void
    {
        $this->query
            ->select('name')->as('productName')
            ->select('price')->as('priceWithVat')
            ->select('brand.name')->as('brand');

        $selectedFields = $this->query->getSelectedFields();
        $this->assertEquals('name', $selectedFields['productName']['originField']);
        $this->assertEquals('price', $selectedFields['priceWithVat']['originField']);
        $this->assertEquals('brand.name', $selectedFields['brand']['originField']);

        foreach ($selectedFields as $data) {
            $this->assertTrue($data['alias']);
            $this->assertInstanceOf(ColumnReferenceNode::class, $data['expression']);
        }
    }

    public function testToDuplicateAliasField(): void
    {
        $this->expectException(Exception\AliasException::class);
        $this->expectExceptionMessage('AS: "brand" already defined');

        $this->query
            ->select('brand.name')->as('brand')
            ->select('name')->as('brand');
    }

    public function testSelectAll(): void
    {
        $this->query->select('*');

        $selectedFields = $this->query->getSelectedFields();
        $this->assertCount(1, $selectedFields);
        // Wildcards keep expression/aggregate null so Stream::applySelect
        // short-circuits to its bespoke merge path.
        $this->assertNull($selectedFields['*']['expression']);
        $this->assertNull($selectedFields['*']['aggregate']);

        $this->query = new TestProvider();
        $this->query->select('id, name, price')
            ->select('*');
        $this->assertCount(4, $this->query->getSelectedFields());

        $this->query = new TestProvider();
        $this->query->select('id, name, price')
            ->select(Query::SELECT_ALL);
        $this->assertCount(4, $this->query->getSelectedFields());
    }

    public function testSelectionSpecialFields(): void
    {
        $this->query
            ->select('`Pripojene produkty (oddelujte znakom "|", max 8 vyrobku)`, `Postovne - priplatky (X,A-O)`')
            ->select('anotherField AS whatever');

        $selectedFields = $this->query->getSelectedFields();
        $this->assertCount(3, $selectedFields);
        $this->assertArrayHasKey('`Pripojene produkty (oddelujte znakom "|", max 8 vyrobku)`', $selectedFields);
        $this->assertArrayHasKey('`Postovne - priplatky (X,A-O)`', $selectedFields);
        $this->assertFalse(
            $selectedFields['`Pripojene produkty (oddelujte znakom "|", max 8 vyrobku)`']['alias']
        );

        $this->assertArrayHasKey('whatever', $selectedFields);
        $this->assertSame('anotherField', $selectedFields['whatever']['originField']);
        $this->assertTrue($selectedFields['whatever']['alias']);
    }

    public function testScalarFunctionHelpersBuildExpressions(): void
    {
        $this->query
            ->select('name')
            ->round('price', 2)->as('roundedPrice')
            ->lower('name')->as('lowerName')
            ->concat('id', 'name')->as('fullId')
            ->year('dateField')->as('yr');

        $selectedFields = $this->query->getSelectedFields();

        // plain column
        $this->assertInstanceOf(ColumnReferenceNode::class, $selectedFields['name']['expression']);

        // each fluent helper produces a FunctionCallNode with the matching name.
        foreach (['roundedPrice' => 'ROUND', 'lowerName' => 'LOWER', 'fullId' => 'CONCAT', 'yr' => 'YEAR'] as $key => $fname) {
            $this->assertArrayHasKey($key, $selectedFields);
            $this->assertInstanceOf(FunctionCallNode::class, $selectedFields[$key]['expression']);
            $this->assertSame($fname, $selectedFields[$key]['expression']->name);
            $this->assertNull($selectedFields[$key]['aggregate']);
        }
    }

    public function testAggregateHelpersPopulateAggregateSlot(): void
    {
        $this->query
            ->sum('price')->as('sumPrice')
            ->count('id')->as('countId')
            ->avg('price')->as('avgPrice')
            ->max('price')->as('maxPrice')
            ->min('price')->as('minPrice')
            ->groupConcat('name', '|')->as('names');

        $selectedFields = $this->query->getSelectedFields();
        $mapping = [
            'sumPrice' => Functions\Aggregate\Sum::class,
            'countId' => Functions\Aggregate\Count::class,
            'avgPrice' => Functions\Aggregate\Avg::class,
            'maxPrice' => Functions\Aggregate\Max::class,
            'minPrice' => Functions\Aggregate\Min::class,
            'names' => Functions\Aggregate\GroupConcat::class,
        ];

        foreach ($mapping as $key => $class) {
            $this->assertArrayHasKey($key, $selectedFields);
            $this->assertNull($selectedFields[$key]['expression']);
            $this->assertIsArray($selectedFields[$key]['aggregate']);
            $this->assertSame($class, $selectedFields[$key]['aggregate']['class']);
        }
        // GROUP_CONCAT keeps the separator in its options payload.
        $this->assertSame('|', $selectedFields['names']['aggregate']['options']['separator']);
    }

    public function testExcludeField(): void
    {
        $this->query->select('id, name, price')
            ->exclude('price');

        $excludedFields = $this->query->getExcludedFields();
        $this->assertCount(1, $excludedFields);
    }
}
