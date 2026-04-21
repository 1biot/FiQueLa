<?php

namespace FQL\Sql\Highlighter;

use FQL\Sql\Token\Token;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

/**
 * Generic Theme-driven highlighter used by both {@see BashHighlighter} and
 * {@see HtmlHighlighter}. The output is the concatenation of each token's raw
 * lexeme (with trivia preserved so whitespace and comments appear verbatim),
 * wrapped in the theme's start/end markers.
 */
class ThemedHighlighter implements Highlighter
{
    public function __construct(private readonly Theme $theme)
    {
    }

    public function highlight(string $sql): string
    {
        $tokens = (new Tokenizer())->tokenize($sql);
        return $this->highlightTokens(new TokenStream($tokens, includeTrivia: true));
    }

    public function highlightTokens(TokenStream $stream): string
    {
        $output = '';
        foreach ($stream as $token) {
            if ($token->type === TokenType::EOF) {
                continue;
            }
            $output .= $this->renderToken($token);
        }
        return $output;
    }

    private function renderToken(Token $token): string
    {
        $content = $this->theme->escape($token->raw);
        $start = $this->theme->styleStart($token->type);
        if ($start === '') {
            return $content;
        }
        return $start . $content . $this->theme->styleEnd($token->type);
    }
}
