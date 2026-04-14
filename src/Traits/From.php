<?php

namespace FQL\Traits;

use FQL\Exception;
use FQL\Interface\Query;

trait From
{
    private ?string $from = null;
    private ?string $fromAlias = null;

    public function from(string $query): Query
    {
        $this->from = $query;
        return $this;
    }

    private function asFrom(string $alias): void
    {
        if ($alias === '') {
            throw new Exception\AliasException('FROM alias cannot be empty');
        }

        if ($this->from === null) {
            throw new Exception\AliasException('Cannot use alias without FROM clause');
        }

        $this->fromAlias = $alias;
    }

    private function getFrom(): string
    {
        return $this->from ?? Query::FROM_ALL;
    }

    private function getFromAlias(): ?string
    {
        return $this->fromAlias;
    }

    private function fromToString(string $source): string
    {
        $fromString = PHP_EOL . sprintf(
            '%s %s%s',
            Query::FROM,
            $source === '' ? '' : ($source . '.'),
            $this->from ?? Query::FROM_ALL
        );

        if ($this->fromAlias !== null) {
            $fromString .= ' ' . Query::AS . ' ' . $this->fromAlias;
        }

        return $fromString;
    }
}
