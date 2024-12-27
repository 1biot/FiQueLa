<?php

namespace UQL\Parser;

use UQL\Enum\LogicalOperator;
use UQL\Enum\Type;
use UQL\Helpers\Types;
use UQL\Query\Query;
use UQL\Enum\Sort;

class Sql implements Parser
{
    /** @var string[] */
    private array $tokens = [];
    private int $position = 0;

    public function parse(string $sql, Query $query): Query
    {
        $this->tokens = (new SqlLexer())->tokenize($sql);
        $this->position = 0;

        while (!$this->isEOF()) {
            $token = $this->nextToken();

            switch (strtoupper($token)) {
                case 'SELECT':
                    $fields = $this->parseFields();
                    $query->select($fields);
                    break;

                case 'FROM':
                    $source = $this->nextToken();
                    $query->from($source);
                    break;

                case 'WHERE':
                    $this->parseConditions($query);
                    break;

                case 'ORDER':
                    $this->expect('BY');
                    $field = $this->nextToken();
                    $direction = strtoupper($this->nextToken()) === 'DESC' ? Sort::DESC : Sort::ASC;
                    $query->orderBy($field, $direction);
                    break;

                case 'LIMIT':
                    $limit = (int)$this->nextToken();
                    $query->limit($limit);
                    break;

                default:
                    throw new \InvalidArgumentException("Unexpected token: $token");
            }
        }

        return $query;
    }

    private function parseFields(): string
    {
        $fields = [];
        while (!$this->isEOF() && strtoupper($this->peekToken()) !== 'FROM') {
            $fields[] = $this->nextToken();
        }
        return implode(' ', $fields);
    }

    private function parseConditions(Query $query): void
    {
        $logicalOperator = LogicalOperator::AND;

        while (!$this->isEOF() && strtoupper($this->peekToken()) !== 'ORDER') {
            $token = strtoupper($this->peekToken());

            if ($token === 'AND') {
                $logicalOperator = LogicalOperator::AND;
                $this->nextToken(); // Consume "AND"
                continue;
            }

            if ($token === 'OR') {
                $logicalOperator = LogicalOperator::OR;
                $this->nextToken(); // Consume "OR"
                continue;
            }

            // Parse a single condition
            $field = $this->nextToken();
            $operator = $this->nextToken();
            $operator = \UQL\Enum\Operator::fromOrFail($operator);
            $value = Type::matchByString($this->nextToken());
            if ($logicalOperator === LogicalOperator::AND) {
                $query->and($field, $operator, $value);
            } else {
                $query->or($field, $operator, $value);
            }
        }
    }

    private function nextToken(): string
    {
        return $this->tokens[$this->position++] ?? '';
    }

    private function peekToken(): string
    {
        return $this->tokens[$this->position] ?? '';
    }

    private function expect(string $expected): void
    {
        $token = $this->nextToken();
        if (strtoupper($token) !== strtoupper($expected)) {
            throw new \InvalidArgumentException("Expected $expected, got $token");
        }
    }

    private function isEOF(): bool
    {
        return $this->position >= count($this->tokens);
    }
}
