<?php

namespace FQL\Traits;

use FQL\Interface\Query;
use FQL\Query\FileQuery;

trait Into
{
    private ?FileQuery $into = null;

    public function into(FileQuery $fileQuery): static
    {
        $this->into = $fileQuery;
        return $this;
    }

    public function hasInto(): bool
    {
        return $this->into !== null;
    }

    public function getInto(): ?FileQuery
    {
        return $this->into;
    }

    private function intoToString(): string
    {
        if ($this->into === null) {
            return '';
        }

        return PHP_EOL . sprintf('%s %s', Query::INTO, (string) $this->into);
    }
}
