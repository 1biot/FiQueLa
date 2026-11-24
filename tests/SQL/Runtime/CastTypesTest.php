<?php

namespace SQL\Runtime;

use FQL\Stream\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CAST over every type name the expression parser accepts, run through FQL
 * so the parser, the evaluator and Functions\Utils\Cast work together.
 */
class CastTypesTest extends TestCase
{
    private const DATA = '{"data":[{"n":12.5,"s":"42","t":"abc","b":"true","z":null}]}';

    private function cast(string $field, string $type): mixed
    {
        $row = Json::string(self::DATA)
            ->fql(sprintf('SELECT CAST(%s AS %s) AS c FROM data', $field, $type))
            ->fetch();
        $this->assertIsArray($row);
        return $row['c'];
    }

    /**
     * @return array<string, array{string, string, mixed}>
     */
    public static function castProvider(): array
    {
        $cases = [];
        foreach (['DOUBLE', 'FLOAT', 'REAL'] as $type) {
            $cases[$type . ' float'] = [$type, 'n', 12.5];
            $cases[$type . ' numeric string'] = [$type, 's', 42.0];
            $cases[$type . ' text'] = [$type, 't', 0.0];
        }
        foreach (['INT', 'INTEGER', 'SIGNED', 'UNSIGNED'] as $type) {
            $cases[$type . ' float'] = [$type, 'n', 12];
            $cases[$type . ' numeric string'] = [$type, 's', 42];
            $cases[$type . ' text'] = [$type, 't', 0];
        }
        foreach (['BOOLEAN', 'BOOL'] as $type) {
            $cases[$type . ' "true"'] = [$type, 'b', true];
        }
        foreach (['CHAR', 'VARCHAR', 'STRING', 'TEXT'] as $type) {
            $cases[$type . ' float'] = [$type, 'n', '12.5'];
            $cases[$type . ' numeric string'] = [$type, 's', '42'];
            $cases[$type . ' text'] = [$type, 't', 'abc'];
        }
        $cases['NULL'] = ['NULL', 'n', null];
        foreach (['DECIMAL', 'NUMERIC', 'NUMBER'] as $type) {
            $cases[$type . ' float'] = [$type, 'n', 12.5];
            $cases[$type . ' numeric string'] = [$type, 's', 42];
            $cases[$type . ' text'] = [$type, 't', 0];
        }
        return $cases;
    }

    #[DataProvider('castProvider')]
    public function testCastToEveryParserType(string $type, string $field, mixed $expected): void
    {
        $this->assertSame($expected, $this->cast($field, $type));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function typeProvider(): array
    {
        $types = [
            'DOUBLE', 'FLOAT', 'REAL', 'INT', 'INTEGER', 'SIGNED', 'UNSIGNED', 'BOOLEAN', 'BOOL',
            'CHAR', 'VARCHAR', 'STRING', 'TEXT', 'NULL', 'DECIMAL', 'NUMERIC', 'NUMBER',
        ];
        return array_combine($types, array_map(fn (string $t) => [$t], $types));
    }

    #[DataProvider('typeProvider')]
    public function testCastOfNullIsNull(string $type): void
    {
        $this->assertNull($this->cast('z', $type));
    }

    public function testCastDecimalInWhere(): void
    {
        $rows = iterator_to_array(
            Json::string(self::DATA)->fql('SELECT n FROM data WHERE CAST(s AS DECIMAL) = 42')->fetchAll()
        );
        $this->assertCount(1, $rows);
    }
}
