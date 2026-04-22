<?php

namespace FQL\Sql\Parser;

/**
 * Shared identifier utilities used by clause parsers. Kept outside the
 * individual parsers so SELECT / FROM / JOIN alias handling stays in lock-
 * step.
 */
final class IdentifierHelper
{
    /**
     * Strips a single pair of outer backticks from a quoted-identifier value
     * (e.g. `Kód objednávky`) — useful when the parser expects a bare alias
     * string. Multi-segment / chained values like `` `a`.`b` `` are returned
     * verbatim since they're invalid aliases; the tokenizer keeps their
     * backticks so the runtime path accessor can still parse them as a
     * column reference.
     */
    public static function stripOuterBackticks(string $value): string
    {
        $len = strlen($value);
        if ($len >= 2 && $value[0] === '`' && $value[$len - 1] === '`') {
            $inner = substr($value, 1, -1);
            if (!str_contains($inner, '`')) {
                return $inner;
            }
        }
        return $value;
    }
}
