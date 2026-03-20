<?php

namespace FQL\Interface;

interface Parser
{
    public function parseWithQuery(Query $query, ?int $startPosition = null): Query;
    public function parse(): Results;
}
