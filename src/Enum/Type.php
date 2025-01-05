<?php

namespace UQL\Enum;

use UQL\Exceptions\InvalidArgumentException;

enum Type: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'int';
    case FLOAT = 'double';
    case STRING = 'string';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case RESOURCE = 'resource';
    case RESOURCE_CLOSED = 'resource (closed)';
    case NULL = 'NULL';
    case UNKNOWN = 'unknown type';

    public static function castValue(mixed $value, ?Type $type = null): mixed
    {
        $type = $type ?? self::matchByValue($value);
        return match ($type) {
            self::STRING, self::UNKNOWN => (string) $value,
            self::INTEGER => (int) $value,
            self::FLOAT => (float) $value,
            self::BOOLEAN => (bool) $value,
            self::NULL => null,
            self::ARRAY => is_array($value) ? $value : [$value],
            self::OBJECT => is_object($value) ? $value : (object) $value,
            default => throw new InvalidArgumentException(
                sprintf('Unsupported type: %s', $type->value)
            )
        };
    }

    public static function matchByValue(mixed $value): self
    {
        return match (gettype($value)) {
            'boolean' => self::BOOLEAN,
            'integer' => self::INTEGER,
            'double' => self::FLOAT,
            'string' => self::STRING,
            'array' => self::ARRAY,
            'object' => self::OBJECT,
            'resource' => self::RESOURCE,
            'resource (closed)' => self::RESOURCE_CLOSED,
            'NULL' => self::NULL,
            'unknown type' => self::UNKNOWN,
        };
    }

    public static function matchByString(string $value): mixed
    {
        // Null
        if (in_array($value, ['null', self::NULL->value])) {
            return null;
        }

        // Boolean
        if (in_array($value, ['true', 'TRUE', 'false', 'FALSE'], true)) {
            return self::castValue(strtolower($value) === 'true' ? 1 : 0, self::BOOLEAN);
        }

        // Integer or Float
        if (self::isNumeric($value)) {
            $type = self::INTEGER;
            $valueToCast = $value;
            if (str_contains($valueToCast, '.') || str_contains($valueToCast, ',')) {
                $valueToCast = str_replace(',', '.', $valueToCast);
                $type = self::FLOAT;
            }

            return self::castValue($valueToCast, $type);
        }

        // String (strip quotes)
        if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
            return self::castValue($matches[1], self::STRING);
        }

        // Fallback to string
        return self::castValue($value, self::STRING);
    }

    private static function isNumeric(string $value): bool
    {
        // Remove thousands separators (space, period, comma)
        $value = preg_replace('/[ ,]/', '', $value);

        // Replace comma with dot for unified decimal separator
        $value = str_replace(',', '.', $value);

        return is_numeric($value);
    }
}
