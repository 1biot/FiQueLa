<?php

namespace Traits;

use FQL\Exception;
use FQL\Functions;
use FQL\Interface\Query;
use FQL\Query\TestProvider;
use PHPUnit\Framework\TestCase;

class SelectTest extends TestCase
{
    /** @var TestProvider $query */
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
            $this->assertNull($data['function']);
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
            $this->assertNull($data['function']);
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
            $this->assertNull($data['function']);
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
        $this->assertCount(0, $selectedFields);

        $this->query->select('id, name, price')
            ->select('*');

        $selectedFields = $this->query->getSelectedFields();
        $this->assertCount(0, $selectedFields);

        $this->query->select('id, name, price')
            ->select(Query::SELECT_ALL);

        $selectedFields = $this->query->getSelectedFields();
        $this->assertCount(0, $selectedFields);
    }

    public function testFunction(): void
    {
        $this->query
            ->select('name')
            ->round('price', 2)->as('roundedPrice')
            ->ceil('price')->as('ceilPrice')
            ->floor('price')->as('floorPrice')
            ->sum('price')->as('sumPrice')
            ->count('id')->as('countId')
            ->max('price')->as('maxPrice')
            ->min('price')->as('minPrice')
            ->modulo('price', 10)->as('modPrice')
            ->avg('price')->as('avgPrice')
            ->length('name')->as('lengthName')
            ->lower('name')->as('lowerName')
            ->upper('name')->as('upperName')
            ->sha1('name')->as('sha1Name')
            ->md5('name')->as('md5Name')
            ->implode('name', '/')->as('implodeNameParts')
            ->glue('name')->as('glueNameParts')
            ->explode('name', ' ')->as('nameParts')
            ->split('name')->as('splitsNameParts')
            ->coalesce('id', 'name', 'price')->as('coalesced')
            ->coalesceNotEmpty('id', 'name', 'price')->as('coalescedNE')
            ->concatWithSeparator(' ', 'name', 'price')->as('concatenatedWS')
            ->concat('id', 'name', 'price')->as('concatenated')
            ->groupConcat('concatenated', '|')->as('groupConcatenated');

        $selectedFields = $this->query->getSelectedFields();
        $this->assertEquals('name', $selectedFields['name']['originField']);
        $this->assertEquals('ROUND(price, 2)', $selectedFields['roundedPrice']['originField']);
        $this->assertEquals('CEIL(price)', $selectedFields['ceilPrice']['originField']);
        $this->assertEquals('FLOOR(price)', $selectedFields['floorPrice']['originField']);
        $this->assertEquals('SUM(price)', $selectedFields['sumPrice']['originField']);
        $this->assertEquals('COUNT(id)', $selectedFields['countId']['originField']);
        $this->assertEquals('MAX(price)', $selectedFields['maxPrice']['originField']);
        $this->assertEquals('MIN(price)', $selectedFields['minPrice']['originField']);
        $this->assertEquals('MOD(price, 10)', $selectedFields['modPrice']['originField']);
        $this->assertEquals('AVG(price)', $selectedFields['avgPrice']['originField']);
        $this->assertEquals('LENGTH(name)', $selectedFields['lengthName']['originField']);
        $this->assertEquals('LOWER(name)', $selectedFields['lowerName']['originField']);
        $this->assertEquals('UPPER(name)', $selectedFields['upperName']['originField']);
        $this->assertEquals('SHA1(name)', $selectedFields['sha1Name']['originField']);
        $this->assertEquals('MD5(name)', $selectedFields['md5Name']['originField']);
        $this->assertEquals('IMPLODE("/", name)', $selectedFields['implodeNameParts']['originField']);
        $this->assertEquals('IMPLODE(",", name)', $selectedFields['glueNameParts']['originField']);
        $this->assertEquals('EXPLODE(" ", name)', $selectedFields['nameParts']['originField']);
        $this->assertEquals('EXPLODE(",", name)', $selectedFields['splitsNameParts']['originField']);
        $this->assertEquals('COALESCE(id, name, price)', $selectedFields['coalesced']['originField']);
        $this->assertEquals('COALESCE_NE(id, name, price)', $selectedFields['coalescedNE']['originField']);
        $this->assertEquals('CONCAT_WS(" ", name, price)', $selectedFields['concatenatedWS']['originField']);
        $this->assertEquals('CONCAT(id, name, price)', $selectedFields['concatenated']['originField']);
        $this->assertEquals('GROUP_CONCAT(concatenated, "|")', $selectedFields['groupConcatenated']['originField']);

        $this->assertNull($selectedFields['name']['function']);

        $this->assertNotNull($selectedFields['roundedPrice']['function']);
        $this->assertInstanceOf(Functions\Math\Round::class, $selectedFields['roundedPrice']['function']);

        $this->assertNotNull($selectedFields['ceilPrice']['function']);
        $this->assertInstanceOf(Functions\Math\Ceil::class, $selectedFields['ceilPrice']['function']);

        $this->assertNotNull($selectedFields['floorPrice']['function']);
        $this->assertInstanceOf(Functions\Math\Floor::class, $selectedFields['floorPrice']['function']);

        $this->assertNotNull($selectedFields['sumPrice']['function']);
        $this->assertInstanceOf(Functions\Aggregate\Sum::class, $selectedFields['sumPrice']['function']);

        $this->assertNotNull($selectedFields['countId']['function']);
        $this->assertInstanceOf(Functions\Aggregate\Count::class, $selectedFields['countId']['function']);

        $this->assertNotNull($selectedFields['maxPrice']['function']);
        $this->assertInstanceOf(Functions\Aggregate\Max::class, $selectedFields['maxPrice']['function']);

        $this->assertNotNull($selectedFields['minPrice']['function']);
        $this->assertInstanceOf(Functions\Aggregate\Min::class, $selectedFields['minPrice']['function']);

        $this->assertNotNull($selectedFields['modPrice']['function']);
        $this->assertInstanceOf(Functions\Math\Mod::class, $selectedFields['modPrice']['function']);

        $this->assertNotNull($selectedFields['avgPrice']['function']);
        $this->assertInstanceOf(Functions\Aggregate\Avg::class, $selectedFields['avgPrice']['function']);

        $this->assertNotNull($selectedFields['lengthName']['function']);
        $this->assertInstanceOf(Functions\String\Length::class, $selectedFields['lengthName']['function']);

        $this->assertNotNull($selectedFields['lowerName']['function']);
        $this->assertInstanceOf(Functions\String\Lower::class, $selectedFields['lowerName']['function']);

        $this->assertNotNull($selectedFields['upperName']['function']);
        $this->assertInstanceOf(Functions\String\Upper::class, $selectedFields['upperName']['function']);

        $this->assertNotNull($selectedFields['sha1Name']['function']);
        $this->assertInstanceOf(Functions\Hashing\Sha1::class, $selectedFields['sha1Name']['function']);

        $this->assertNotNull($selectedFields['md5Name']['function']);
        $this->assertInstanceOf(Functions\Hashing\Md5::class, $selectedFields['md5Name']['function']);

        $this->assertNotNull($selectedFields['implodeNameParts']['function']);
        $this->assertInstanceOf(Functions\String\Implode::class, $selectedFields['implodeNameParts']['function']);

        $this->assertNotNull($selectedFields['glueNameParts']['function']);
        $this->assertInstanceOf(Functions\String\Implode::class, $selectedFields['glueNameParts']['function']);

        $this->assertNotNull($selectedFields['nameParts']['function']);
        $this->assertInstanceOf(Functions\String\Explode::class, $selectedFields['nameParts']['function']);

        $this->assertNotNull($selectedFields['splitsNameParts']['function']);
        $this->assertInstanceOf(Functions\String\Explode::class, $selectedFields['splitsNameParts']['function']);

        $this->assertNotNull($selectedFields['coalesced']['function']);
        $this->assertInstanceOf(Functions\Utils\Coalesce::class, $selectedFields['coalesced']['function']);

        $this->assertNotNull($selectedFields['coalescedNE']['function']);
        $this->assertInstanceOf(Functions\Utils\CoalesceNotEmpty::class, $selectedFields['coalescedNE']['function']);

        $this->assertNotNull($selectedFields['concatenatedWS']['function']);
        $this->assertInstanceOf(Functions\String\ConcatWS::class, $selectedFields['concatenatedWS']['function']);

        $this->assertNotNull($selectedFields['concatenated']['function']);
        $this->assertInstanceOf(Functions\String\Concat::class, $selectedFields['concatenated']['function']);

        $this->assertNotNull($selectedFields['groupConcatenated']['function']);
        $this->assertInstanceOf(
            Functions\Aggregate\GroupConcat::class,
            $selectedFields['groupConcatenated']['function']
        );
    }
}
