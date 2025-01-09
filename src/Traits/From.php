<?php

namespace UQL\Traits;

use UQL\Query\Query;

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
        return sprintf(
            PHP_EOL . "%s %s%s",
            Query::FROM,
            $source === '' ? '' : ($source . '.'),
            $this->from ?? Query::FROM_ALL
        );
    }
}
