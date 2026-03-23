<?php

namespace Query;

use FQL\Query\Provider;
use PHPUnit\Framework\TestCase;

class ProviderFactoryTest extends TestCase
{
    public function testFromFileQueryCsvAppliesOptions(): void
    {
        $csvFile = sys_get_temp_dir() . '/fiquela-provider-' . uniqid() . '.csv';
        file_put_contents($csvFile, "name;price\nA;10\n");

        $query = Provider::fromFileQuery(sprintf('csv(%s, "windows-1250", ";")', $csvFile));

        $fileQuery = (string) $query->provideFileQuery();
        $expected = sprintf('csv(%s, "windows-1250", ";")', basename($csvFile));

        $this->assertSame($expected, $fileQuery);

        unlink($csvFile);
    }

    public function testFqlCreatesQuery(): void
    {
        $jsonPath = realpath(__DIR__ . '/../../examples/data/products.json');
        $sql = sprintf('SELECT * FROM json(%s).data.products', $jsonPath);

        $query = Provider::fql($sql);

        $this->assertStringContainsString('SELECT', (string) $query);
    }
}
