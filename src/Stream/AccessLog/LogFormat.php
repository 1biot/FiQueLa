<?php

namespace FQL\Stream\AccessLog;

use FQL\Exception\InvalidFormatException;

final class LogFormat
{
    /**
     * @var array<string, array{literal?: string, group?: string, pattern?: string, type?: string}>
     */
    private const TOKEN_MAP = [
        '%%'  => ['literal' => '%'],
        '%>s' => ['group' => 'status',              'pattern' => '\\d{3}',  'type' => 'int'],
        '%A'  => ['group' => 'localIp',             'pattern' => '\\S+',    'type' => 'string'],
        '%a'  => ['group' => 'remoteIp',            'pattern' => '\\S+',    'type' => 'string'],
        '%b'  => ['group' => 'responseBytes',       'pattern' => '\\S+',    'type' => 'int_nullable'],
        '%D'  => ['group' => 'timeServeRequest',    'pattern' => '\\d+',    'type' => 'microseconds'],
        '%h'  => ['group' => 'host',                'pattern' => '\\S+',    'type' => 'string'],
        '%I'  => ['group' => 'receivedBytes',       'pattern' => '\\d+',    'type' => 'int'],
        '%l'  => ['group' => 'logname',             'pattern' => '\\S+',    'type' => 'string_nullable'],
        '%m'  => ['group' => 'requestMethod',       'pattern' => '\\S+',    'type' => 'string'],
        '%O'  => ['group' => 'sentBytes',           'pattern' => '\\d+',    'type' => 'int'],
        '%p'  => ['group' => 'port',                'pattern' => '\\d+',    'type' => 'int'],
        '%r'  => ['group' => 'request',             'pattern' => '\\S+',    'type' => 'request'],
        '%S'  => ['group' => 'scheme',              'pattern' => '\\S+',    'type' => 'string'],
        '%T'  => ['group' => 'requestTime',         'pattern' => '[\\d.]+', 'type' => 'float'],
        '%t'  => ['group' => 'time',                'pattern' => '\\S+',    'type' => 'time'],
        '%u'  => ['group' => 'user',                'pattern' => '\\S+',    'type' => 'string_nullable'],
        '%U'  => ['group' => 'url',                 'pattern' => '\\S+',    'type' => 'string'],
        '%v'  => ['group' => 'serverName',          'pattern' => '\\S+',    'type' => 'string'],
        '%V'  => ['group' => 'canonicalServerName', 'pattern' => '\\S+',    'type' => 'string'],
    ];

    /** @var array<string, string> */
    private const PROFILES = [
        'nginx_combined'  => '%h - %u [%t] "%r" %>s %b "%{Referer}i" "%{User-Agent}i"',
        'nginx_main'      => '%h - %u [%t] "%r" %>s %b',
        'apache_combined' => '%h %l %u [%t] "%r" %>s %b "%{Referer}i" "%{User-Agent}i"',
        'apache_common'   => '%h %l %u [%t] "%r" %>s %b',
    ];

    /** @var array<string, array{regex: string, fields: list<string>, types: array<string, string>}> */
    private static array $cache = [];

    public static function hasProfile(string $profileName): bool
    {
        return isset(self::PROFILES[$profileName]);
    }

    /**
     * @return list<string>
     */
    public static function getAvailableProfiles(): array
    {
        return array_keys(self::PROFILES);
    }

    /**
     * @throws InvalidFormatException
     */
    public static function getProfileRegex(string $profileName): string
    {
        return self::resolve($profileName)['regex'];
    }

    /**
     * @return list<string>
     * @throws InvalidFormatException
     */
    public static function getProfileFields(string $profileName): array
    {
        return self::resolve($profileName)['fields'];
    }

    /**
     * @return array{regex: string, fields: list<string>, types: array<string, string>}
     * @throws InvalidFormatException
     */
    public static function resolveProfile(string $profileName): array
    {
        return self::resolve($profileName);
    }

    /**
     * @return array{regex: string, fields: list<string>, types: array<string, string>}
     * @throws InvalidFormatException
     */
    public static function logFormatToRegex(string $format): array
    {
        if (isset(self::$cache[$format])) {
            return self::$cache[$format];
        }

        $regex = '';
        $fields = [];
        $types = [];
        $groupCounts = [];
        $pos = 0;
        $len = strlen($format);

        while ($pos < $len) {
            $char = $format[$pos];

            if ($char !== '%') {
                if ($char === '[') {
                    $regex .= '\\[';
                } elseif ($char === ']') {
                    $regex .= '\\]';
                } elseif ($char === '"') {
                    $regex .= '"';
                } else {
                    $regex .= preg_quote($char, '/');
                }
                $pos++;
                continue;
            }

            // %% literal
            if ($pos + 1 < $len && $format[$pos + 1] === '%') {
                $regex .= '%';
                $pos += 2;
                continue;
            }

            // %>s (3-char token)
            if ($pos + 2 < $len && $format[$pos + 1] === '>') {
                $token = substr($format, $pos, 3);
                if (!isset(self::TOKEN_MAP[$token])) {
                    throw new InvalidFormatException(sprintf('Unknown log format token "%s"', $token));
                }
                $info = self::TOKEN_MAP[$token];
                if (!isset($info['group'])) {
                    throw new InvalidFormatException(sprintf('Token "%s" has no group mapping', $token));
                }
                $group = $info['group'];
                $pattern = $info['pattern'];
                $type = $info['type'];

                $actualName = self::uniqueGroupName($group, $groupCounts);
                $regex .= '(?P<' . $actualName . '>' . $pattern . ')';
                $fields[] = $actualName;
                $types[$actualName] = $type;
                $pos += 3;
                continue;
            }

            // %{...}i or %{...}p (dynamic token)
            if ($pos + 1 < $len && $format[$pos + 1] === '{') {
                $closingBrace = strpos($format, '}', $pos + 2);
                if ($closingBrace === false) {
                    throw new InvalidFormatException('Unclosed brace in log format token');
                }
                if ($closingBrace + 1 >= $len) {
                    throw new InvalidFormatException('Missing suffix after closing brace in log format token');
                }

                $varName = substr($format, $pos + 2, $closingBrace - $pos - 2);
                $suffix = $format[$closingBrace + 1];

                if ($suffix === 'i') {
                    $group = strtolower(str_replace('-', '_', $varName));
                    $type = 'string_nullable';
                } elseif ($suffix === 'p') {
                    $group = 'port';
                    $type = 'int';
                } else {
                    throw new InvalidFormatException(
                        sprintf('Unknown variable suffix "%s" in log format token', $suffix)
                    );
                }

                // Context-dependent pattern
                $prevChar = $pos > 0 ? $format[$pos - 1] : '';
                $pattern = $prevChar === '"' ? '[^"]*' : '\\S+';

                $actualName = self::uniqueGroupName($group, $groupCounts);
                $regex .= '(?P<' . $actualName . '>' . $pattern . ')';
                $fields[] = $actualName;
                $types[$actualName] = $type;
                $pos = $closingBrace + 2;
                continue;
            }

            // Standard 2-char token (%h, %u, %t, etc.)
            $token = substr($format, $pos, 2);
            if (!isset(self::TOKEN_MAP[$token])) {
                throw new InvalidFormatException(sprintf('Unknown log format token "%s"', $token));
            }

            $info = self::TOKEN_MAP[$token];
            if (isset($info['literal'])) {
                $regex .= preg_quote($info['literal'], '/');
                $pos += 2;
                continue;
            }

            if (!isset($info['group'])) {
                throw new InvalidFormatException(sprintf('Token "%s" has no group mapping', $token));
            }
            $group = $info['group'];
            $pattern = $info['pattern'];
            $type = $info['type'];

            // Context-dependent pattern overrides
            $prevChar = $pos > 0 ? $format[$pos - 1] : '';
            if ($group === 'request' && $prevChar === '"') {
                $pattern = '[^"]*';
            } elseif ($group === 'time' && $prevChar === '[') {
                $pattern = '[^\\]]+';
            }

            $actualName = self::uniqueGroupName($group, $groupCounts);
            $regex .= '(?P<' . $actualName . '>' . $pattern . ')';
            $fields[] = $actualName;
            $types[$actualName] = $type;
            $pos += 2;
        }

        $result = [
            'regex' => '/^' . $regex . '$/',
            'fields' => $fields,
            'types' => $types,
        ];

        self::$cache[$format] = $result;

        return $result;
    }

    /**
     * @param array<string, string> $matches
     * @param list<string> $fields
     * @param array<string, string> $types
     * @return array<string, string|int|float|null>
     */
    public static function normalizeValues(array $matches, array $fields, array $types): array
    {
        $result = [];

        foreach ($fields as $field) {
            $value = $matches[$field] ?? null;
            $type = $types[$field] ?? 'string';

            if ($value === null) {
                $result[$field] = null;
                continue;
            }

            $result[$field] = self::normalizeValue($value, $type);
        }

        // Expand request field into method, path, protocol
        foreach ($fields as $field) {
            $type = $types[$field] ?? 'string';
            if ($type === 'request' && isset($result[$field])) {
                $requestValue = (string) $result[$field];
                if ($requestValue === '-') {
                    $result['method'] = null;
                    $result['path'] = null;
                    $result['protocol'] = null;
                } else {
                    $parts = explode(' ', $requestValue, 3);
                    $result['method'] = $parts[0] ?? null;
                    $result['path'] = $parts[1] ?? null;
                    $result['protocol'] = $parts[2] ?? null;
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, null>
     * @param list<string> $fields
     * @param array<string, string> $types
     */
    public static function nullRow(array $fields, array $types): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = null;
        }

        // Add request expansion fields if request type is present
        foreach ($fields as $field) {
            $type = $types[$field] ?? 'string';
            if ($type === 'request') {
                $result['method'] = null;
                $result['path'] = null;
                $result['protocol'] = null;
            }
        }

        return $result;
    }

    /**
     * @param array<string, int> $groupCounts
     */
    private static function uniqueGroupName(string $group, array &$groupCounts): string
    {
        if (!isset($groupCounts[$group])) {
            $groupCounts[$group] = 1;
            return $group;
        }

        $groupCounts[$group]++;
        return $group . '_' . $groupCounts[$group];
    }

    private static function normalizeValue(string $value, string $type): string|int|float|null
    {
        return match ($type) {
            'int' => (int) $value,
            'int_nullable' => $value === '-' ? null : (int) $value,
            'float' => (float) $value,
            'microseconds' => (float) $value / 1000.0,
            'time' => self::normalizeTime($value),
            'string_nullable' => $value === '-' ? null : $value,
            'request', 'string' => $value,
            default => $value,
        };
    }

    private static function normalizeTime(string $value): string
    {
        $dt = \DateTimeImmutable::createFromFormat('d/M/Y:H:i:s O', $value);
        if ($dt === false) {
            return $value;
        }

        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * @return array{regex: string, fields: list<string>, types: array<string, string>}
     * @throws InvalidFormatException
     */
    private static function resolve(string $profileName): array
    {
        if (!isset(self::PROFILES[$profileName])) {
            throw new InvalidFormatException(
                sprintf(
                    'Unknown log format profile "%s". Available: %s',
                    $profileName,
                    implode(', ', array_keys(self::PROFILES))
                )
            );
        }

        return self::logFormatToRegex(self::PROFILES[$profileName]);
    }
}
