<?php

namespace FQL\Traits;

trait Describable
{
    private bool $describeMode = false;

    public function isDescribeMode(): bool
    {
        return $this->describeMode;
    }

    public function isDescribeEmpty(): bool
    {
        return !$this->describeMode;
    }

    private function enableDescribe(): void
    {
        $this->describeMode = true;
    }
}
