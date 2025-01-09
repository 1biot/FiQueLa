<?php

namespace UQL\Traits\Helpers;

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
}
