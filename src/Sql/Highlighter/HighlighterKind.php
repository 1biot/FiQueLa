<?php

namespace FQL\Sql\Highlighter;

enum HighlighterKind: string
{
    case BASH = 'bash';
    case HTML = 'html';
}
