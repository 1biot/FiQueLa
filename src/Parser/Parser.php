<?php

namespace FQL\Parser;

use FQL\Query\Query;

interface Parser
{
    public function parse(string $sql, Query $query): Query;
}
