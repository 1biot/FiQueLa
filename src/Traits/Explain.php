<?php

namespace FQL\Traits;

use FQL\Interface;

trait Explain
{
    private bool $explain = false;

    public function explain(bool $explain = true): Interface\Query
    {
        $this->explain = $explain;
        return $this;
    }

    public function isExplain(): bool
    {
        return $this->explain;
    }

    private function explainToString(): string
    {
        return $this->explain ? Interface\Query::EXPLAIN : '';
    }
}
