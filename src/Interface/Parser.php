<?php

namespace FQL\Interface;

/**
 * @deprecated Use {@see \FQL\Sql\Compiler} (via {@see \FQL\Sql\Provider::compile()}).
 * Retained only while the legacy {@see \FQL\Sql\Sql} parser is still in tree.
 */
interface Parser
{
    public function parseWithQuery(Query $query, ?int $startPosition = null): Query;
    public function parse(): Results;
}
