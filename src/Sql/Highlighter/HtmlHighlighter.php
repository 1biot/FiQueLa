<?php

namespace FQL\Sql\Highlighter;

/**
 * HTML highlighter that wraps each token in a `<span class="fql-…">` — see
 * {@see HtmlTheme} for the default class names. Pair with the stylesheet at
 * `examples/highlighter.css` (or bring your own).
 */
final class HtmlHighlighter extends ThemedHighlighter
{
    public function __construct(?Theme $theme = null)
    {
        parent::__construct($theme ?? new HtmlTheme());
    }
}
