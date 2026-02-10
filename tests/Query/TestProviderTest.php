<?php

namespace Query;

use FQL\Enum\Operator;
use FQL\Query\TestProvider;
use PHPUnit\Framework\TestCase;

class TestProviderTest extends TestCase
{
    public function testAccessorsExposeState(): void
    {
        $provider = new TestProvider();
        $provider->resetConditions();

        $provider->select('id')
            ->exclude('name')
            ->from('items')
            ->limit(2, 1)
            ->where('id', Operator::EQUAL, 1);

        $this->assertArrayHasKey('id', $provider->getSelectedFields());
        $this->assertSame(['name'], $provider->getExcludedFields());
        $this->assertSame([2, 1], $provider->getLimitAndOffset());
        $this->assertSame('items', $provider->getFromSource());
        $this->assertSame('items', (string) $provider->provideFileQuery());
    }

    public function testResetConditionsClearsGroups(): void
    {
        $provider = new TestProvider();
        $provider->resetConditions();
        $provider->where('id', Operator::EQUAL, 1);

        $result = $provider->resetConditions();

        $this->assertSame($provider, $result);
    }
}
