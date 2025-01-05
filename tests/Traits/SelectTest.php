<?php

namespace UQL\Traits;

use PHPUnit\Framework\TestCase;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Functions\Ceil;
use UQL\Functions\Coalesce;
use UQL\Functions\CoalesceNotEmpty;
use UQL\Functions\Concat;
use UQL\Functions\ConcatWS;
use UQL\Functions\Explode;
use UQL\Functions\Floor;
use UQL\Functions\Implode;
use UQL\Functions\Length;
use UQL\Functions\Lower;
use UQL\Functions\Md5;
use UQL\Functions\Round;
use UQL\Functions\Sha1;
use UQL\Functions\Upper;
use UQL\Query\Query;
use UQL\Query\TestProvider;

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
        $this->query->select('id, name, price');

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
            ->select('price');

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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alias "brand" already defined');

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
            ->concat('id', 'name', 'price')->as('concatenated');

        $selectedFields = $this->query->getSelectedFields();
        $this->assertEquals('name', $selectedFields['name']['originField']);
        $this->assertEquals('ROUND(price, 2)', $selectedFields['roundedPrice']['originField']);
        $this->assertEquals('CEIL(price)', $selectedFields['ceilPrice']['originField']);
        $this->assertEquals('FLOOR(price)', $selectedFields['floorPrice']['originField']);
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

        $this->assertNull($selectedFields['name']['function']);

        $this->assertNotNull($selectedFields['roundedPrice']['function']);
        $this->assertInstanceOf(Round::class, $selectedFields['roundedPrice']['function']);

        $this->assertNotNull($selectedFields['ceilPrice']['function']);
        $this->assertInstanceOf(Ceil::class, $selectedFields['ceilPrice']['function']);

        $this->assertNotNull($selectedFields['floorPrice']['function']);
        $this->assertInstanceOf(Floor::class, $selectedFields['floorPrice']['function']);

        $this->assertNotNull($selectedFields['lengthName']['function']);
        $this->assertInstanceOf(Length::class, $selectedFields['lengthName']['function']);

        $this->assertNotNull($selectedFields['lowerName']['function']);
        $this->assertInstanceOf(Lower::class, $selectedFields['lowerName']['function']);

        $this->assertNotNull($selectedFields['upperName']['function']);
        $this->assertInstanceOf(Upper::class, $selectedFields['upperName']['function']);

        $this->assertNotNull($selectedFields['sha1Name']['function']);
        $this->assertInstanceOf(Sha1::class, $selectedFields['sha1Name']['function']);

        $this->assertNotNull($selectedFields['md5Name']['function']);
        $this->assertInstanceOf(Md5::class, $selectedFields['md5Name']['function']);

        $this->assertNotNull($selectedFields['implodeNameParts']['function']);
        $this->assertInstanceOf(Implode::class, $selectedFields['implodeNameParts']['function']);

        $this->assertNotNull($selectedFields['glueNameParts']['function']);
        $this->assertInstanceOf(Implode::class, $selectedFields['glueNameParts']['function']);

        $this->assertNotNull($selectedFields['nameParts']['function']);
        $this->assertInstanceOf(Explode::class, $selectedFields['nameParts']['function']);

        $this->assertNotNull($selectedFields['splitsNameParts']['function']);
        $this->assertInstanceOf(Explode::class, $selectedFields['splitsNameParts']['function']);

        $this->assertNotNull($selectedFields['coalesced']['function']);
        $this->assertInstanceOf(Coalesce::class, $selectedFields['coalesced']['function']);

        $this->assertNotNull($selectedFields['coalescedNE']['function']);
        $this->assertInstanceOf(CoalesceNotEmpty::class, $selectedFields['coalescedNE']['function']);

        $this->assertNotNull($selectedFields['concatenatedWS']['function']);
        $this->assertInstanceOf(ConcatWS::class, $selectedFields['concatenatedWS']['function']);

        $this->assertNotNull($selectedFields['concatenated']['function']);
        $this->assertInstanceOf(Concat::class, $selectedFields['concatenated']['function']);
    }
}
