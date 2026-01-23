<?php

namespace FQL\Enum;

use FQL\Exception\InvalidArgumentException;

enum Type: string
{
    case BOOLEAN = 'boolean';
    case TRUE = 'true';
    case FALSE = 'false';

    case NUMBER = 'number';
    case INTEGER = 'int';
    case FLOAT = 'double';

    case STRING = 'string';
    case NULL = 'null';

    case ARRAY = 'array';
    case OBJECT = 'object';

    case RESOURCE = 'resource';
    case RESOURCE_CLOSED = 'resource (closed)';

    case UNKNOWN = 'unknown';

    public static function castValue(mixed $value, ?Type $type = null): mixed
    {
        $type = $type ?? self::match($value);
        return match ($type) {
            self::STRING, self::UNKNOWN => self::toString($value),
            self::INTEGER => is_numeric($value) ? (int) $value : 0,
            self::FLOAT => is_numeric($value) ? (float) $value : 0.0,
            self::BOOLEAN => (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            self::NULL => null,
            self::ARRAY => is_array($value) ? $value : [$value],
            self::OBJECT => is_object($value) ? $value : null,
            default => throw new InvalidArgumentException(
                sprintf('Unsupported type: %s', $type->value)
            )
        };
    }

    public static function match(mixed $value): self
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
        if (in_array($value, ['null', 'NULL'])) {
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

    /**
     * @return Type[]
     */
    public static function listValues(): array
    {
        return [
            self::BOOLEAN,
            self::INTEGER,
            self::FLOAT,
            self::STRING,
            self::ARRAY,
            self::OBJECT,
            self::RESOURCE,
            self::RESOURCE_CLOSED,
            self::NULL,
            self::UNKNOWN,
        ];
    }

    private static function isNumeric(string $value): bool
    {
        $value = trim($value); // Trim whitespace from both ends
        $value = preg_replace('/[ ]/', '', $value) ?? ''; // Remove spaces (used as thousand separators)
        $value = str_replace(',', '.', $value); // Normalize decimal separator to dot
        if (substr_count($value, '.') > 1) {
            return false; // More than one decimal point — invalid numeric format
        }

        if (preg_match('/[.,]$/', $value)) {
            return false; // Ends with a dot or comma — likely invalid
        }

        return is_numeric($value); // Final numeric check
    }

    private static function toString(mixed $value): string
    {
        $type = self::match($value);
        try {
            return match ($type) {
                self::NULL => 'null',
                self::TRUE => 'true',
                self::FALSE => 'false',
                self::ARRAY => json_encode($value),
                self::OBJECT => $value instanceof \Serializable ? $value->serialize() : 'object',
                self::STRING => $value,
                default => (string) $value,
            };
        } catch (\Exception) {
            return 'null';
        }
    }
}
