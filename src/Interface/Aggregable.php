<?php

namespace FQL\Interface;

interface Aggregable
{
    public function sum(string $key): float;

    public function avg(string $key, int $decimalPlaces = 2): float;

    public function min(string $key): float;

    public function max(string $key): float;
}
