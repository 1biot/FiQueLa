<?php

namespace FQL\Interfaces;

interface Parser
{
    public function parse(string $sql, Query $query): Query;
}
