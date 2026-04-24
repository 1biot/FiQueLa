<?php

namespace FQL\Sql\Highlighter;

/**
 * ANSI-coloured highlighter for terminal output. See {@see BashTheme} for the default
 * palette; construct with a custom Theme to override colours.
 */
final class BashHighlighter extends ThemedHighlighter
{
    public function __construct(?Theme $theme = null)
    {
        parent::__construct($theme ?? new BashTheme());
    }
}
