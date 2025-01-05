<?php

namespace UQL\Helpers;

class StringHelper
{
    public static function camelCaseToUpperSnakeCase(string $input): string
    {
        $snake = preg_replace('/(?<!^)([A-Z](?=[A-Z]|[a-z]))/', '_$1', $input);
        $snake = preg_replace('/([a-z])([A-Z])/', '$1_$2', $snake);
        return strtoupper($snake);
    }
}
