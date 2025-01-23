<?php

namespace FQL\Traits\Helpers;

trait StringOperations
{
    public function camelCaseToUpperSnakeCase(string $input): string
    {
        // Return input if it is already in the correct format
        if (strtoupper($input) === $input && !preg_match('/[a-z]/', $input) && !preg_match('/_{2,}/', $input)) {
            return $input;
        }

        // Add underscores before uppercase letters, unless at the start or after an underscore
        $snake = preg_replace('/(?<!^|_|[A-Z])([A-Z])/', '_$1', $input);

        // Consolidate multiple underscores into one
        $snake = preg_replace('/_+/', '_', $snake);

        // Convert to uppercase and return the result
        return strtoupper($snake);
    }

    public function isQuoted(string $input): bool
    {
        return preg_match('/^".*"$/', $input) === 1 || preg_match('/^\'.*\'$/', $input) === 1;
    }

    public function removeQuotes(string $input): string
    {
        return substr($input, 1, -1);
    }

    public function extractPlainText(string $input): string
    {
        // remove code blocks (multi-line and single-line)
        $cleaned = preg_replace([
            '/```[\s\S]*?```/m', // multi-line code blocks: ```code```
            '/`{1,2}(.*?)`{1,2}/', // single-line code: `inline code`
        ], '', $input);

        // remove all HTML tags
        $cleaned = strip_tags($cleaned);

        // remove Markdown syntax
        $cleaned = preg_replace([
            '/\*\*(.*?)\*\*/',       // Bold: **text**
            '/\*(.*?)\*/',           // Italic: *text*
            '/_(.*?)_/',           // Italic: _text_
            '/\[(.*?)]\(.*?\)/',    // Links: [text](url)
            '/!\[.*?]\(.*?\)/',    // Images: ![alt](url)
            '/^#{1,6}\s+(.*)$/m',    // Headers: # Header, ## Header
            '/^([*\-+])\s+(.*)$/m', // Unordered list: * item, - item, + item
            '/^\d+\.\s+(.*)$/m',     // Ordered list: 1. item
            '/\|.*?\|/m',            // Table rows: | col1 | col2 |
            '/-{3,}/',               // Horizontal rules: ---
        ], '$1', $cleaned);

        // allowed characters: letters, numbers, spaces, common punctuation
        $cleaned = preg_replace('/[^\p{L}\p{N}\s.,!?;:\'\"-]/u', '', $cleaned);

        // normalize multiple spaces to one
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        return strtolower(trim($cleaned));
    }
}
