<?php

namespace FQL\Sql;

use FQL\Conditions\Condition;
use FQL\Enum;
use FQL\Exception\SortException;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions;
use FQL\Interface\Parser;
use FQL\Interface\Query;
use FQL\Traits\Helpers\StringOperations;

class Sql implements Parser
{
    use StringOperations;

    private const CONTROL_KEYWORDS = ['SELECT', 'FROM', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET'];

    /** @var string[] */
    private array $tokens = [];
    private int $position = 0;

    /**
     * @throws UnexpectedValueException
     */
    public function parse(string $sql, Query $query): Query
    {
        $this->position = 0;
        $this->tokens = (new SqlLexer())->tokenize($sql);
        while (!$this->isEOF()) {
            $token = $this->nextToken();
            switch (strtoupper($token)) {
                case 'SELECT':
                    $this->parseFields($query);
                    break;

                case 'FROM':
                    $source = $this->nextToken();
                    $source = preg_replace('/(\[([a-z]+:\/\/)?(.*)])(.*)/', '$4', $source);
                    $query->from(trim($source, '.'));
                    break;

                case 'HAVING':
                case 'WHERE':
                    $this->parseConditions($query, strtolower($token));
                    break;

                case 'GROUP':
                    $this->expect('BY');
                    $this->parseGroupBy($query);
                    break;

                case 'ORDER':
                    $this->expect('BY');
                    $this->parseSort($query);
                    break;

                case 'OFFSET':
                    $limit = (int) $this->nextToken();
                    $query->offset($limit);
                    break;

                case 'LIMIT':
                    $limit = (int) $this->nextToken();
                    $query->limit($limit);
                    break;

                default:
                    throw new UnexpectedValueException("Unexpected token: $token");
            }
        }

        return $query;
    }

    private function parseFields(Query $query): void
    {
        while (!$this->isEOF() && !$this->isNextControlledKeyword()) {
            $field = $this->nextToken();
            if ($field === ',') {
                continue;
            } elseif ($field === 'DISTINCT') {
                $query->distinct();
                continue;
            }

            if ($this->isFunction($field)) {
                $this->applyFunctionToQuery($field, $query);
            } else {
                $query->select($field);
            }

            if (strtoupper($this->peekToken()) === 'AS') {
                $this->nextToken();
                $alias = $this->nextToken();
                $query->as($alias);
            }
        }
    }

    private function isFunction(string $token): bool
    {
        return preg_match('/\b(?!_)[A-Z0-9_]{2,}(?<!_)\(.*?\)/i', $token) === 1;
    }

    private function getFunction(string $token): string
    {
        return preg_replace('/(\b(?!_)[A-Z0-9_]{2,}(?<!_))\(.*?\)/i', '$1', $token);
    }

    /**
     * @param string $token
     * @return array<scalar|null>
     */
    private function getFunctionArguments(string $token): array
    {
        preg_match('/\b(?!_)[A-Z0-9_]{2,}(?<!_)\((.*?)\)/i', $token, $matches);
        return array_values(
            array_filter(
                array_map(
                    fn ($value) => $this->isQuoted($value) ? $this->removeQuotes($value) : $value,
                    array_map('trim', explode(',', $matches[1] ?? ''))
                )
            )
        );
    }

    /**
     * @param string $field
     * @param Query $query
     * @return void
     */
    private function applyFunctionToQuery(string $field, Query $query): void
    {
        $functionName = $this->getFunction($field);
        $arguments = $this->getFunctionArguments($field);

        match (strtoupper($functionName)) {
            // aggregate
            'AVG' => $query->avg((string) ($arguments[0] ?? '')),
            'COUNT' => $query->count((string) ($arguments[0] ?? '')),
            'GROUP_CONCAT' => $query->groupConcat((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? ',')),
            'MAX' => $query->max((string) ($arguments[0] ?? '')),
            'MIN' => $query->min((string) ($arguments[0] ?? '')),
            'SUM' => $query->sum((string) ($arguments[0] ?? '')),

            // hashing
            'MD5' => $query->md5((string) ($arguments[0] ?? '')),
            'SHA1' => $query->sha1((string) ($arguments[0] ?? '')),

            // math
            'CEIL' => $query->ceil((string) ($arguments[0] ?? '')),
            'FLOOR' => $query->floor((string) ($arguments[0] ?? '')),
            'MOD' => $query->modulo((string) ($arguments[0] ?? ''), (int) ($arguments[1] ?? 0)),
            'ROUND' => $query->round((string) ($arguments[0] ?? ''), (int) ($arguments[1] ?? 0)),

            // string
            'BASE64_DECODE' => $query->toBase64((string) ($arguments[0] ?? '')),
            'BASE64_ENCODE' => $query->fromBase64((string) ($arguments[0] ?? '')),
            'CONCAT' => $query->concat(...$arguments),
            'CONCAT_WS' => $query->concatWithSeparator((string) ($arguments[0] ?? ''), ...array_slice($arguments, 1)),
            'EXPLODE' => $query->explode((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? ',')),
            'IMPLODE' => $query->implode((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? ',')),
            'LENGTH' => $query->length((string) ($arguments[0] ?? '')),
            'LOWER' => $query->lower((string) ($arguments[0] ?? '')),
            'RANDOM_STRING' => $query->randomString((int) ($arguments[0] ?? 10)),
            'REVERSE' => $query->reverse((string) ($arguments[0] ?? '')),
            'UPPER' => $query->upper((string) ($arguments[0] ?? '')),

            // utils
            'COALESCE' => $query->coalesce(...$arguments),
            'COALESCE_NE' => $query->coalesceNotEmpty(...$arguments),
            'RANDOM_BYTES' => $query->randomBytes((int) ($arguments[0] ?? 10)),
            default => throw new UnexpectedValueException("Unknown function: $functionName"),
        };
    }

    private function parseConditions(Query $query, string $context): void
    {
        $logicalOperator = Enum\LogicalOperator::AND;
        $firstIter = true;
        while (!$this->isEOF() && !$this->isNextControlledKeyword()) {
            $token = strtoupper($this->peekToken());

            if ($token === 'AND') {
                $logicalOperator = Enum\LogicalOperator::AND;
                $this->nextToken(); // Consume "AND"
                continue;
            }

            if ($token === 'OR') {
                $logicalOperator = Enum\LogicalOperator::OR;
                $this->nextToken(); // Consume "OR"
                continue;
            }

            if ($token === 'XOR') {
                $logicalOperator = Enum\LogicalOperator::XOR;
                $this->nextToken(); // Consume "OR"
                continue;
            }

            // Parse a single condition
            $field = $this->nextToken();
            $operator = $this->nextToken();
            if (in_array($operator, ['IS', 'NOT', 'LIKE', 'IN'])) {
                $nextToken = $this->nextToken();
                if (in_array($nextToken, ['NOT', 'LIKE', 'IN'])) {
                    $operator .= ' ' . $nextToken;
                } else {
                    $this->rewindToken();
                }
            }

            $operator = Enum\Operator::fromOrFail($operator);
            $value = Enum\Type::matchByString($this->nextToken());
            if ($firstIter && $context === Condition::WHERE && $logicalOperator === Enum\LogicalOperator::AND) {
                $query->where($field, $operator, $value);
                $firstIter = false;
                continue;
            } elseif ($firstIter && $context === Condition::HAVING && $logicalOperator === Enum\LogicalOperator::AND) {
                $query->having($field, $operator, $value);
                $firstIter = false;
                continue;
            }

            if ($logicalOperator === Enum\LogicalOperator::AND) {
                $query->and($field, $operator, $value);
            } elseif ($logicalOperator === Enum\LogicalOperator::OR) {
                $query->or($field, $operator, $value);
            } else {
                $query->xor($field, $operator, $value);
            }
        }
    }

    private function parseGroupBy(Query $query): void
    {
        while (!$this->isEOF() && !$this->isNextControlledKeyword()) {
            $field = $this->nextToken();
            if ($field === ',') {
                continue;
            }

            $query->groupBy($field);
        }
    }

    private function parseSort(Query $query): void
    {
        while (!$this->isEOF() && !$this->isNextControlledKeyword()) {
            $field = $this->nextToken();
            if ($field === ',') {
                continue;
            }

            $directionString = strtoupper($this->nextToken());
            $direction = match ($directionString) {
                'ASC' => Enum\Sort::ASC,
                'DESC' => Enum\Sort::DESC,
                'SHUFFLE' => Enum\Sort::SHUFFLE,
                'NATSORT' => Enum\Sort::NATSORT,
                default => throw new SortException(sprintf('Invalid direction %s', $directionString)),
            };
            $query->orderBy($field, $direction);
        }
    }

    private function nextToken(): string
    {
        return $this->tokens[$this->position++] ?? '';
    }

    private function rewindToken(): string
    {
        return $this->tokens[$this->position--] ?? '';
    }

    private function peekToken(): string
    {
        return $this->tokens[$this->position] ?? '';
    }

    /**
     * @throws UnexpectedValueException
     */
    private function expect(string $expected): void
    {
        $token = $this->nextToken();
        if (strtoupper($token) !== strtoupper($expected)) {
            throw new UnexpectedValueException("Expected $expected, got $token");
        }
    }

    private function isEOF(): bool
    {
        return $this->position >= count($this->tokens);
    }

    private function isNextControlledKeyword(): bool
    {
        return in_array(strtoupper($this->peekToken()), self::CONTROL_KEYWORDS);
    }
}
