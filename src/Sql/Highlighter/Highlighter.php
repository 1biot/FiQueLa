<?php

namespace FQL\Sql\Highlighter;

use FQL\Sql\Token\TokenStream;

/**
 * Renders an FQL source string into a styled representation (ANSI, HTML, ...).
 *
 * The default pipeline is: `highlight($sql)` tokenises with trivia preserved and
 * delegates to `highlightTokens()`, so consumers can also feed in a pre-built
 * TokenStream (e.g. for caching or custom pre-processing).
 */
interface Highlighter
{
    public function highlight(string $sql): string;

    public function highlightTokens(TokenStream $stream): string;
}
