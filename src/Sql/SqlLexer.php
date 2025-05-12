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
        $pattern = '/(?<=^|\s)(' . implode('|', self::CONTROL_KEYWORDS) . ')\b/i';
        preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            $this->tokens = $this->defaultTokenize($sql);
            return $this->tokens;
        }

        $count = count($matches[0]);
        $lastOffset = 0;

        for ($i = 0; $i < $count; $i++) {
            $keyword = strtoupper($matches[1][$i][0]);
            $start = $matches[0][$i][1];

            // tokenize content before the current keyword
            $prefix = trim(substr($sql, $lastOffset, $start - $lastOffset));
            if ($prefix !== '') {
                $this->tokens = array_merge($this->tokens, $this->defaultTokenize($prefix));
            }

            $this->tokens[] = $keyword;

            // get content for current keyword block
            $nextStart = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($sql);
            $chunk = trim(substr($sql, $start + strlen($keyword), $nextStart - $start - strlen($keyword)));

            if ($keyword === 'FROM') {
                $this->tokens = array_merge($this->tokens, $this->sourceTokenize($chunk));
            } elseif ($keyword === 'JOIN') {
                $joinParts = preg_split('/\b(AS|ON)\b/i', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $partCount = count($joinParts !== false ? $joinParts : []);

                for ($j = 0; $j < $partCount; $j++) {
                    $part = trim($joinParts[$j] ?? '');
                    $upper = strtoupper($part);

                    if ($upper === 'AS' || $upper === 'ON') {
                        $this->tokens[] = $upper;
                        $j++;
                        if (isset($joinParts[$j])) {
                            $this->tokens = array_merge($this->tokens, $this->defaultTokenize(trim($joinParts[$j])));
                        }
                    } else {
                        $this->tokens = array_merge($this->tokens, $this->sourceTokenize($part));
                    }
                }
            } else {
                $this->tokens = array_merge($this->tokens, $this->defaultTokenize($chunk));
            }

            $lastOffset = $nextStart;
        }

        // tokenize any trailing content
        $remaining = trim(substr($sql, $lastOffset));
        if ($remaining !== '') {
            $this->tokens = array_merge($this->tokens, $this->defaultTokenize($remaining));
        }

        return $this->tokens;
    }


    /**
     * @return string[]
     */
    private function defaultTokenize(string $chunk): array
    {
        $regex = '/(
            \b(?!_)[A-Z0-9_]{2,}(?<!_)\((?:[^()`\'"]+|`[^`]*`|\'[^\']*\'|"[^"]*")*\)  # function calls
            | \'[^\']*\' | \"[^\"]*\"                                                 # string literals
            | (?:`[^`]+`|[^\s`,()])+                                                  # data accessor - dot chains
            | \(|\)                                                                   # parentheses
            | [^\s(),]+                                                               # all other non-whitespace tokens
        )/uxi';

        preg_match_all($regex, $chunk, $matches);
        return array_values(array_filter(array_map('trim', $matches[0]), fn ($t) => $t !== ''));
    }

    /**
     * @return string[]
     */
    private function sourceTokenize(string $chunk): array
    {
        $regex = '/(' . FileQuery::getRegexp(13) . ')/uxi';
        preg_match_all($regex, $chunk, $matches);
        return array_values(array_filter(array_map('trim', $matches[0]), fn ($t) => $t !== ''));
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
     * @throws Exception\UnexpectedValueException
     * @return array{0: string, 1: Enum\Operator, 2: Enum\Type|float|int|string|string[]}
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

        if ($value === null || is_bool($value)) {
            throw new Exception\UnexpectedValueException(
                'For compare NULL or BOOLEAN value, use IS or IS NOT operator'
            );
        }

        return [$field, $operator, $value];
    }

    protected function isFunction(string $token): bool
    {
        return preg_match('/\b(?!_)[A-Z0-9_]{2,}(?<!_)\((?:[^()`\'"]+|`[^`]*`|\'[^\']*\'|"[^"]*")*\)/i', $token) === 1;
    }

    protected function getFunction(string $token): string
    {
        preg_match('/\b(?!_)[A-Z0-9_]{2,}(?<!_)/i', $token, $matches);
        return $matches[0] ?? '';
    }

    /**
     * @param string $token
     * @return array<scalar|null>
     */
    protected function getFunctionArguments(string $token): array
    {
        preg_match('/\b(?!_)[A-Z0-9_]{2,}(?<!_)\(((?:[^()`\'"]+|`[^`]*`|\'[^\']*\'|"[^"]*")*)\)/i', $token, $matches);
        return $this->parserArgumentsFromParentheses($matches[1] ?? '');
    }

    /**
     * @param string $token
     * @return string[]
     */
    protected function parserArgumentsFromParentheses(string $token): array
    {
        return array_values(
            array_map(
                fn ($value) => $this->isQuoted($value) ? $this->removeQuotes($value) : $value,
                array_filter(
                    array_map('trim', explode(',', trim($token, '()'))),
                    fn ($value) => $value !== ''
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
