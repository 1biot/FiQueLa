<?php

namespace FQL\Sql\Formatter;

/**
 * Configures SqlFormatter output style.
 *
 * Defaults produce a standard "pretty" layout:
 *   - 4-space indent
 *   - keywords upper-cased
 *   - one clause per line, fields on separate lines when more than one
 */
final readonly class FormatterOptions
{
    public function __construct(
        public string $indent = '    ',
        public bool $uppercaseKeywords = true,
        public bool $fieldsPerLine = true,
        public string $newline = "\n"
    ) {
    }
}
