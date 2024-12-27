<?php

namespace UQL\Parser;

use UQL\Query\Query;

interface Parser
{
    public function parse(string $sql, Query $query): Query;
}
