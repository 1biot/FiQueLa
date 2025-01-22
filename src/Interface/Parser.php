<?php

namespace FQL\Interface;

interface Parser
{
    public function parse(string $sql, Query $query): Query;
}
