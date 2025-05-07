<?php

namespace FQL\Sql;

use FQL\Enum;
use FQL\Exception;
use FQL\Query\FileQuery;
use FQL\Traits;

/**
 * @implements \Iterator<string>
 */
class SqlLexer implements \Iterator
{
    use Traits\Helpers\StringOperations;

    private const CONTROL_KEYWORDS = [
        'SELECT', 'FROM', 'INNER', 'LEFT', 'JOIN',
        'WHERE', 'GROUP', 'HAVING', 'ORDER',
        'LIMIT', 'OFFSET'
    ];

    /**
     * @var string[]
     */
    protected array $tokens = [];
    protected int $position = 0;

    /**
     * @param string $sql
     * @return string[]
     */
    public function tokenize(string $sql): array
    {
        $regex = '/(
            \b(?!_)[A-Z0-9_]{2,}(?<!_)\(.*?\)    # function calls (e.g., FUNC_1(arg1)) - name must follow rules
            | ' . FileQuery::getRegexp(13) . '   # File Query regexp
            | \'[^\']*\' | \"[^\"]*\"            # string literals
            | (?:`[^`]+`|[^\s`,()])+             # full accessor (dot chains, [] etc.)
            | \(|\)                              # parentheses
            | [^\s(),]+                         # all other non-whitespace tokens
        )/uxi';

        preg_match_all($regex, $sql, $matches);
        // Remove empty tokens and trim
        $this->tokens = array_values(array_filter(array_map('trim', $matches[0]), fn ($value) => $value !== ''));
        return $this->tokens;
    }

    protected function nextToken(): string
    {
        return $this->tokens[$this->position++] ?? '';
    }

    protected function rewindToken(): string
    {
        return $this->tokens[$this->position--] ?? '';
    }

    protected function peekToken(): string
    {
        return $this->tokens[$this->position] ?? '';
    }

    /**
     * @throws Exception\UnexpectedValueException
     */
    protected function expect(string $expected): void
    {
        $token = $this->nextToken();
        if (strtoupper($token) !== strtoupper($expected)) {
            throw new Exception\UnexpectedValueException("Expected $expected, got $token");
        }
    }

    protected function isEOF(): bool
    {
        return $this->position >= count($this->tokens);
    }

    protected function isNextControlledKeyword(): bool
    {
        return in_array(strtoupper($this->peekToken()), self::CONTROL_KEYWORDS);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return $this->isEOF();
    }

    public function next(): void
    {
        $this->nextToken();
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function current(): mixed
    {
        return $this->peekToken();
    }

    /**
     * @return array{0: string, 1: Enum\Operator, 2: Enum\Type|scalar|null|string[]}
     */
    public function parseSingleCondition(): array
    {
        $field = $this->nextToken();
        $operator = $this->nextToken();
        $upperOperator = mb_strtoupper($operator);
        if (in_array($upperOperator, ['IS', 'NOT', 'LIKE', 'IN'])) {
            $nextToken = $this->nextToken();
            $upperNextToken = mb_strtoupper($nextToken);
            if (in_array($upperNextToken, ['NOT', 'LIKE', 'IN'])) {
                $operator = $upperOperator . ' ' . $upperNextToken;
                $upperOperator .= ' ' . $upperNextToken;
            } else {
                $operator = $upperOperator;
                $this->rewindToken();
            }
        }

        $operator = Enum\Operator::fromOrFail($operator);
        if (str_contains($upperOperator, 'IN')) {
            $value = $this->parserArgumentsFromParentheses($this->nextToken());
        } else {
            $value = $this->nextToken();
            $value = $operator === Enum\Operator::IS || $operator === Enum\Operator::NOT_IS
                ? Enum\Type::from(strtolower($value))
                : Enum\Type::matchByString($value);
        }

        return [$field, $operator, $value];
    }

    protected function isFunction(string $token): bool
    {
        return preg_match('/\b(?!_)[A-Z0-9_]{2,}(?<!_)\(.*?\)/i', $token) === 1;
    }

    protected function getFunction(string $token): string
    {
        return preg_replace('/(\b(?!_)[A-Z0-9_]{2,}(?<!_))\(.*?\)/i', '$1', $token) ?? '';
    }

    /**
     * @param string $token
     * @return array<scalar|null>
     */
    protected function getFunctionArguments(string $token): array
    {
        preg_match('/\b(?!_)[A-Z0-9_]{2,}(?<!_)(\(.*?\))/i', $token, $matches);
        return $this->parserArgumentsFromParentheses($matches[1] ?? '');
    }

    /**
     * @param string $token
     * @return string[]
     */
    protected function parserArgumentsFromParentheses(string $token): array
    {
        return array_values(
            array_filter(
                array_map(
                    fn ($value) => $this->isQuoted($value) ? $this->removeQuotes($value) : $value,
                    array_map('trim', explode(',', trim($token, '()')))
                )
            )
        );
    }

    /**
     * @return array{0: string|null, 1: Enum\Fulltext}
     */
    protected function parseSearchMode(string $input): array
    {
        $pattern = '/^([\'"])(.*?)\1\s+IN\s+(NATURAL|BOOLEAN)\s+MODE$/i';
        if (!preg_match($pattern, $input, $matches)) {
            throw new Exception\QueryLogicException('Invalid AGAINST syntax');
        }

        $searchQuery = trim($matches[2]);
        return [$searchQuery === '' ? null : $searchQuery, Enum\Fulltext::from($matches[3])];
    }
}
