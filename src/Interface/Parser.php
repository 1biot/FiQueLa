<?php

namespace FQL\Interface;

interface Parser
{
    public function parseWithQuery(Query $query): Query;
    public function parse(): Results;
}
