<?php

namespace FQL\Sql\Ast;

enum ExplainMode: string
{
    case NONE = 'NONE';
    case EXPLAIN = 'EXPLAIN';
    case EXPLAIN_ANALYZE = 'EXPLAIN_ANALYZE';
}
