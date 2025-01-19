<?php

namespace FQL\Traits;

use FQL\Interfaces\Query;

trait From
{
    private ?string $from = null;

    public function from(string $query): Query
    {
        $this->from = $query;
        return $this;
    }

    private function getFrom(): string
    {
        return $this->from ?? Query::FROM_ALL;
    }

    private function fromToString(string $source): string
    {
        return PHP_EOL . sprintf(
            '%s %s%s',
            Query::FROM,
            $source === '' ? '' : ($source . '.'),
            $this->from ?? Query::FROM_ALL
        );
    }
}
