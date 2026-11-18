<?php

namespace FQL\Sql\Printer;

/**
 * Letter case of keywords (and function names, TRUE / FALSE / NULL) written by the printers.
 */
enum KeywordCase: string
{
    case UPPER = 'upper';
    case LOWER = 'lower';

    public function apply(string $keyword): string
    {
        return $this === self::LOWER ? strtolower($keyword) : strtoupper($keyword);
    }
}
