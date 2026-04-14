<?php

namespace FQL\Sql;

use FQL\Conditions;
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
        'SELECT', 'DESCRIBE', 'FROM', 'INTO', 'INNER', 'LEFT', 'JOIN',
        'WHERE', 'GROUP', 'HAVING', 'ORDER',
        'LIMIT', 'OFFSET', 'UNION'
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
        $sql = $this->removeComments($sql);
        $pattern = '/(?<=^|\s)(' . implode('|', self::CONTROL_KEYWORDS) . ')\b/i';
        preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE);

        // Filter out control keywords that are inside parentheses (subqueries)
        $matches = $this->filterKeywordsInsideParentheses($sql, $matches);

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
                $fromParts = preg_split('/\b(AS)\b/i', $chunk, 2, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                if ($fromParts !== false) {
                    $this->tokens = array_merge($this->tokens, $this->sourceTokenize(trim($fromParts[0])));
                    for ($j = 1; $j < count($fromParts); $j++) {
                        $part = trim($fromParts[$j]);
                        $upper = strtoupper($part);
                        if ($upper === 'AS') {
                            $this->tokens[] = $upper;
                        } else {
                            $this->tokens = array_merge($this->tokens, $this->defaultTokenize($part));
                        }
                    }
                }
            } elseif ($keyword === 'INTO' || $keyword === 'DESCRIBE') {
                $this->tokens = array_merge($this->tokens, $this->sourceTokenize($chunk));
            } elseif ($keyword === 'JOIN') {
                $chunk = trim($chunk);
                if (str_starts_with($chunk, '(')) {
                    // Subquery join: extract balanced parens, tokenize inner as full SQL
                    [$inner, $remaining] = $this->extractBalancedParens($chunk);
                    $this->tokens[] = '(';
                    $subLexer = new self();
                    $this->tokens = array_merge($this->tokens, $subLexer->tokenize($inner));
                    $this->tokens[] = ')';
                    // Remaining: " AS alias ON condition"
                    $remainParts = preg_split(
                        '/\b(AS|ON)\b/i',
                        $remaining,
                        -1,
                        PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
                    );
                    foreach ($remainParts !== false ? $remainParts : [] as $part) {
                        $part = trim($part);
                        $upper = strtoupper($part);
                        if ($upper === 'AS' || $upper === 'ON') {
                            $this->tokens[] = $upper;
                        } elseif ($part !== '') {
                            $this->tokens = array_merge($this->tokens, $this->defaultTokenize($part));
                        }
                    }
                } else {
                    // FileQuery join: existing logic
                    $joinParts = preg_split(
                        '/\b(AS|ON)\b/i',
                        $chunk,
                        -1,
                        PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
                    );
                    $partCount = count($joinParts !== false ? $joinParts : []);

                    for ($j = 0; $j < $partCount; $j++) {
                        $part = trim($joinParts[$j] ?? '');
                        $upper = strtoupper($part);

                        if ($upper === 'AS' || $upper === 'ON') {
                            $this->tokens[] = $upper;
                            $j++;
                            if (isset($joinParts[$j])) {
                                $this->tokens = array_merge(
                                    $this->tokens,
                                    $this->defaultTokenize(trim($joinParts[$j]))
                                );
                            }
                        } else {
                            $this->tokens = array_merge($this->tokens, $this->sourceTokenize($part));
                        }
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
     * Filters out keyword matches that are inside parentheses (subqueries).
     * @param string $sql
     * @param array<int, array<int, array{0: string, 1: int}>> $matches
     * @return array<int, array<int, array{0: string, 1: int}>>
     */
    private function filterKeywordsInsideParentheses(string $sql, array $matches): array
    {
        if (empty($matches[0])) {
            return $matches;
        }

        // Build a depth map: for each position in $sql, compute parenthesis depth
        $depth = 0;
        $depthAtPos = [];
        $inQuote = null;
        for ($i = 0, $len = strlen($sql); $i < $len; $i++) {
            $char = $sql[$i];
            if ($inQuote !== null) {
                if ($char === $inQuote) {
                    $inQuote = null;
                }
                $depthAtPos[$i] = $depth;
                continue;
            }
            if ($char === '"' || $char === '\'' || $char === '`') {
                $inQuote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            }
            $depthAtPos[$i] = $depth;
        }

        // Filter: keep only matches at depth 0
        $filtered = [[], []];
        foreach ($matches[0] as $idx => $match) {
            $pos = $match[1];
            if (($depthAtPos[$pos] ?? 0) === 0) {
                $filtered[0][] = $match;
                $filtered[1][] = $matches[1][$idx];
            }
        }

        return $filtered;
    }

    /**
     * Extracts content inside balanced parentheses from the beginning of a string.
     * @return array{0: string, 1: string} [innerContent, remaining]
     */
    private function extractBalancedParens(string $chunk): array
    {
        $depth = 0;
        $inQuote = null;
        for ($i = 0, $len = strlen($chunk); $i < $len; $i++) {
            $char = $chunk[$i];
            if ($inQuote !== null) {
                if ($char === $inQuote) {
                    $inQuote = null;
                }
                continue;
            }
            if ($char === '"' || $char === '\'' || $char === '`') {
                $inQuote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $inner = substr($chunk, 1, $i - 1);
                    $remaining = trim(substr($chunk, $i + 1));
                    return [$inner, $remaining];
                }
            }
        }

        // Unbalanced — return everything as inner
        return [substr($chunk, 1), ''];
    }

    private function removeComments(string $sql): string
    {
        // Remove multi-line comments (/* ... */)
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? '';

        // Remove single-line comments (-- and #), but not inside quotes
        $lines = explode("\n", $sql);
        foreach ($lines as &$line) {
            // ignore if inside string
            if (preg_match('/^[^\'"`]*(--|#)/', $line, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[1][1];
                $line = substr($line, 0, $pos);
            }
        }
        return implode("\n", $lines);
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
        $regex = '/(' . FileQuery::getRegexp() . ')/uxi';
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
        if (in_array($upperOperator, ['IS', 'NOT', 'LIKE', 'IN', 'BETWEEN', 'REGEXP'])) {
            $nextToken = $this->nextToken();
            $upperNextToken = mb_strtoupper($nextToken);
            if (in_array($upperNextToken, ['NOT', 'LIKE', 'IN', 'BETWEEN', 'REGEXP'])) {
                $operator = $upperOperator . ' ' . $upperNextToken;
                $upperOperator .= ' ' . $upperNextToken;
            } else {
                $operator = $upperOperator;
                $this->rewindToken();
            }
        }

        $operator = Enum\Operator::fromOrFail($operator);
        if (str_contains($upperOperator, 'IN')) {
            $this->expect('(');
            $value = [];
            while ($this->peekToken() !== ')') {
                $value[] = Enum\Type::matchByString($this->peekToken());
                $this->nextToken();
            }
            $this->nextToken();
        } elseif (str_contains($upperOperator, 'BETWEEN')) {
            $value = [$this->nextToken()];
            $this->expect('AND');
            $value[] = $this->nextToken();
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

    /**
     * @template T of Conditions\BaseConditionGroup
     * @param T $rootConditionGroup
     * @param callable(string, int): bool|null $stopParser
     * @return T
     */
    public function parseConditionGroup(
        Conditions\BaseConditionGroup $rootConditionGroup,
        ?callable $stopParser = null
    ): Conditions\BaseConditionGroup {
        $currentGroup = $rootConditionGroup;
        $logicalOperator = Enum\LogicalOperator::AND;
        $depth = 0;

        while (!$this->isEOF()) {
            $token = $this->peekToken();
            if ($stopParser !== null && $stopParser($token, $depth)) {
                break;
            }

            $upperToken = mb_strtoupper($token);
            if (in_array($upperToken, Enum\LogicalOperator::casesValues(), true)) {
                $logicalOperator = Enum\LogicalOperator::from($upperToken);
                $this->nextToken();
                continue;
            }

            if ($token === '(') {
                $this->nextToken();
                $group = new Conditions\GroupCondition($logicalOperator, $currentGroup);
                $currentGroup->addCondition($logicalOperator, $group);
                $currentGroup = $group;
                $depth++;
                $logicalOperator = Enum\LogicalOperator::AND;
                continue;
            }

            if ($token === ')') {
                if ($depth === 0) {
                    throw new Exception\UnexpectedValueException('Unexpected closing parenthesis in condition group');
                }

                $this->nextToken();
                $parent = $currentGroup->getParent();
                if ($parent === null) {
                    throw new Exception\UnexpectedValueException('Missing parent condition group');
                }

                $currentGroup = $parent;
                $depth--;
                $logicalOperator = Enum\LogicalOperator::AND;
                continue;
            }

            [$field, $operator, $value] = $this->parseSingleCondition();
            $currentGroup->addCondition(
                $logicalOperator,
                new Conditions\SimpleCondition($logicalOperator, $field, $operator, $value)
            );
            $logicalOperator = Enum\LogicalOperator::AND;
        }

        if ($depth !== 0) {
            throw new Exception\UnexpectedValueException('Unclosed parenthesis in condition group');
        }

        return $rootConditionGroup;
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
        $token = trim($token, '()');
        $args = [];
        $current = '';
        $quoteChar = null;
        $length = strlen($token);

        for ($i = 0; $i < $length; $i++) {
            $char = $token[$i];
            if ($quoteChar !== null) {
                $current .= $char;
                if ($char === $quoteChar) {
                    $quoteChar = null;
                }
                continue;
            }

            if ($char === ',' && $quoteChar === null) {
                $args[] = trim($current);
                $current = '';
                continue;
            }

            if ($char === '"' || $char === '\'' || $char === '`') {
                $quoteChar = $char;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $args[] = trim($current);
        }

        return array_values(
            array_map(
                fn ($value) => $this->isQuoted($value) ? $this->removeQuotes($value) : $value,
                array_filter($args, fn (string $v) => $v !== '')
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
