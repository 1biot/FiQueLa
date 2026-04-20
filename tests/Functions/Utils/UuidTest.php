<?php

namespace Functions\Utils;

use FQL\Functions\Utils\Uuid;
use FQL\Sql\Provider as SqlProvider;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class UuidTest extends TestCase
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testUuidReturnsValidV4(): void
    {
        $uuid = new Uuid();
        $result = $uuid();

        $this->assertIsString($result);
        $this->assertSame(36, strlen($result));
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $result);
    }

    public function testUuidIsUnique(): void
    {
        $uuid = new Uuid();
        $this->assertNotSame($uuid(), $uuid());
    }

    public function testUuidToString(): void
    {
        $uuid = new Uuid();
        $this->assertSame('UUID()', (string) $uuid);
    }

    public function testUuidFluentApi(): void
    {
        $json = Json::string(json_encode([['id' => 1]]));
        $query = $json->query()->uuid()->as('uid');

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertArrayHasKey('uid', $rows[0]);
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $rows[0]['uid']);
    }

    public function testUuidFqlParsing(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../../examples/data/products.json');
        $sql = sprintf(
            'SELECT id, UUID() AS uid FROM json(%s).data.products LIMIT 1',
            $jsonFile
        );

        $results = SqlProvider::compile($sql)->toQuery()->execute();
        $rows = iterator_to_array($results->fetchAll());

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('uid', $rows[0]);
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $rows[0]['uid']);
    }
}
