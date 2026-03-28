<?php

namespace FQL\Sql;

use FQL\Conditions\CaseStatementConditionGroup;
use FQL\Conditions\HavingConditionGroup;
use FQL\Conditions\WhereConditionGroup;
use FQL\Enum;
use FQL\Exception;
use FQL\Functions;
use FQL\Interface;
use FQL\Query;
use FQL\Utils\FileQueryPathValidator;

class Sql extends SqlLexer implements Interface\Parser
{
    public function __construct(private readonly string $sql, private ?string $basePath = null)
    {
        $this->tokenize($this->sql);
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function parse(): Interface\Results
    {
        return $this->toQuery()->execute();
    }

    /**
     * @throws Exception\FileNotFoundException
     * @throws Exception\InvalidFormatException
     */
    public function toQuery(?int $startPosition = null): Interface\Query
    {
        if ($startPosition !== null) {
            $this->position = $startPosition;
        } else {
            $this->rewind();
        }

        while (!$this->isEOF()) {
            $token = $this->nextToken();
            $upper = strtoupper($token);

            if ($upper !== 'FROM' && $upper !== 'DESCRIBE') {
                continue;
            }

            $fileQuery = $this->validateFileQueryPath($this->nextToken());
            $query = Query\Provider::fromFileQuery((string) $fileQuery);
            $parseStart = $startPosition !== null ? $startPosition : null;
            return $this->parseWithQuery($query, $parseStart);
        }

        throw new Exception\UnexpectedValueException('Undefined file in query');
    }

    /**
     * @throws Exception\UnexpectedValueException
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function parseWithQuery(Interface\Query $query, ?int $startPosition = null): Interface\Query
    {
        if ($startPosition !== null) {
            $this->position = $startPosition;
        } else {
            $this->rewind();
        }
        while (!$this->isEOF()) {
            $token = $this->nextToken();
            switch (strtoupper($token)) {
                case Interface\Query::EXPLAIN:
                    if (strtoupper($this->peekToken()) === Interface\Query::ANALYZE) {
                        $this->nextToken();
                        $query->explainAnalyze();
                    } else {
                        $query->explain();
                    }
                    break;
                case Interface\Query::DESCRIBE:
                    $this->nextToken(); // consume the FileQuery token (already resolved in toQuery)
                    $query->describe();
                    break;
                case Interface\Query::SELECT:
                    $this->parseFields($query);
                    break;

                case Interface\Query::FROM:
                    $fileQuery = $this->validateFileQueryPath($this->nextToken());
                    $query->from($fileQuery->query ?? '');
                    break;
                case Interface\Query::INTO:
                    $intoFileQuery = $this->validateFileQueryPath($this->nextToken(), mustExist: false);
                    $query->into($intoFileQuery);
                    break;
                case 'LEFT':
                case 'RIGHT':
                case 'FULL':
                    if (strtoupper($this->nextToken()) === 'OUTER') {
                        $this->nextToken(); // Consume "JOIN"
                    }

                    $joinQuery = $this->validateFileQueryPath($this->nextToken());
                    $this->expect(Interface\Query::AS);
                    $alias = $this->nextToken();

                    if (strtolower($token) === 'left') {
                        $query->leftJoin(Query\Provider::fromFileQuery((string) $joinQuery), $alias);
                    } elseif (strtolower($token) === 'right') {
                        $query->rightJoin(Query\Provider::fromFileQuery((string) $joinQuery), $alias);
                    } elseif (strtolower($token) === 'full') {
                        $query->fullJoin(Query\Provider::fromFileQuery((string) $joinQuery), $alias);
                    }

                    $this->expect(Interface\Query::ON);
                    $field = $this->nextToken();
                    $operator = Enum\Operator::fromOrFail($this->nextToken());
                    $value = Enum\Type::matchByString($this->nextToken());

                    $query->on($field, $operator, $value);
                    break;
                case 'INNER':
                case 'JOIN':
                    if (strtoupper($token) === 'INNER') {
                        $this->nextToken(); // Consume "JOIN"
                    }
                    $joinQuery = $this->validateFileQueryPath($this->nextToken());
                    $this->expect(Interface\Query::AS);
                    $alias = $this->nextToken();

                    $query->innerJoin(Query\Provider::fromFileQuery((string) $joinQuery), $alias);
                    $this->expect(Interface\Query::ON);

                    $field = $this->nextToken();
                    $operator = Enum\Operator::fromOrFail($this->nextToken());
                    $value = Enum\Type::matchByString($this->nextToken());

                    $query->on($field, $operator, $value);
                    break;

                case Interface\Query::HAVING:
                    $this->parseHavingConditions($query);
                    break;
                case Interface\Query::WHERE:
                    $this->parseWhereConditions($query);
                    break;

                case 'GROUP':
                    $this->expect(Interface\Query::BY);
                    $this->parseGroupBy($query);
                    break;

                case 'ORDER':
                    $this->expect(Interface\Query::BY);
                    $this->parseSort($query);
                    break;

                case Interface\Query::OFFSET:
                    $limit = (int) $this->nextToken();
                    $query->offset($limit);
                    break;

                case Interface\Query::LIMIT:
                    $limit = (int) $this->nextToken();
                    $offset = $this->nextToken();
                    $query->limit($limit, $offset === '' ? null : (int) $offset);
                    break;

                case 'UNION':
                    $isUnionAll = strtoupper($this->peekToken()) === 'ALL';
                    if ($isUnionAll) {
                        $this->nextToken(); // consume ALL
                    }
                    $unionStartPosition = $this->position;
                    $unionQuery = $this->toQuery($unionStartPosition);
                    $isUnionAll ? $query->unionAll($unionQuery) : $query->union($unionQuery);
                    break;

                default:
                    throw new Exception\UnexpectedValueException("Unexpected token: $token");
            }
        }

        return $query;
    }

    private function parseFields(Interface\Query $query): void
    {
        $mode = 'selectIn';
        while (!$this->isEOF() && !$this->isNextControlledKeyword()) {
            $field = $this->nextToken();
            if ($field === ',') {
                continue;
            } elseif (strtoupper($field) === Interface\Query::DISTINCT) {
                $query->distinct();
                continue;
            } elseif (strtoupper($field) === Interface\Query::CASE) {
                $query->case();
                do {
                    $this->expect(Interface\Query::WHEN);
                    $conditionGroup = $this->parseConditionGroup(
                        new CaseStatementConditionGroup(),
                        fn (string $token, int $depth): bool => $depth === 0
                            && strtoupper($token) === Interface\Query::THEN
                    );
                    $this->expect(Interface\Query::THEN);
                    $query->whenCase($conditionGroup->render(), $this->nextToken());
                    if (strtoupper($this->nextToken()) !== Interface\Query::ELSE) {
                        $this->rewindToken();
                        continue;
                    }

                    $query->elseCase($this->nextToken());
                } while ($this->peekToken() !== Interface\Query::END);
                $this->expect(Interface\Query::END);
                $query->endCase();
                if (strtoupper($this->nextToken()) === Interface\Query::AS) {
                    $query->as($this->nextToken());
                } else {
                    $this->rewindToken();
                }
                continue;
            } elseif (strtoupper($field) === Interface\Query::EXCLUDE) {
                $mode = 'selectOut';
                continue;
            }

            if ($mode === 'selectOut') {
                $query->exclude($field);
            } elseif ($mode === 'selectIn') {
                if ($this->isFunction($field)) {
                    $this->applyFunctionToQuery($field, $query);
                } else {
                    $query->select($field);
                }

                if (strtoupper($this->peekToken()) === Interface\Query::AS) {
                    $this->nextToken();
                    $alias = $this->nextToken();
                    $query->as($alias);
                }
            }
        }
    }

    /**
     * @param string $field
     * @param Interface\Query $query
     * @return void
     */
    private function applyFunctionToQuery(string $field, Interface\Query $query): void
    {
        $functionName = $this->getFunction($field);
        $arguments = $this->getFunctionArguments($field);
        $distinct = false;

        if (isset($arguments[0]) && is_string($arguments[0])) {
            if (preg_match('/^DISTINCT\s+(.+)$/i', $arguments[0], $matches) === 1) {
                $distinct = true;
                $arguments[0] = $matches[1];
            }
        }

        match (strtoupper($functionName)) {
            // aggregate
            'AVG' => $query->avg((string) ($arguments[0] ?? '')),
            'COUNT' => $query->count((string) ($arguments[0] ?? ''), $distinct),
            'GROUP_CONCAT' => $query->groupConcat(
                (string) ($arguments[0] ?? ''),
                (string) ($arguments[1] ?? ','),
                $distinct
            ),
            'MAX' => $query->max((string) ($arguments[0] ?? ''), $distinct),
            'MIN' => $query->min((string) ($arguments[0] ?? ''), $distinct),
            'SUM' => $query->sum((string) ($arguments[0] ?? ''), $distinct),

            // hashing
            'MD5' => $query->md5((string) ($arguments[0] ?? '')),
            'SHA1' => $query->sha1((string) ($arguments[0] ?? '')),

            // math
            'CEIL' => $query->ceil((string) ($arguments[0] ?? '')),
            'FLOOR' => $query->floor((string) ($arguments[0] ?? '')),
            'MOD' => $query->modulo((string) ($arguments[0] ?? ''), (int) ($arguments[1] ?? 0)),
            'ROUND' => $query->round((string) ($arguments[0] ?? ''), (int) ($arguments[1] ?? 0)),
            'ADD' => $query->add(
                ...array_map(
                    fn ($value) => Enum\Type::castValue($value, Enum\Type::STRING),
                    $arguments
                )
            ),
            'SUB' => $query->subtract(
                ...array_map(
                    fn ($value) => Enum\Type::castValue($value, Enum\Type::STRING),
                    $arguments
                )
            ),
            'MULTIPLY' => $query->multiply(
                ...array_map(
                    fn ($value) => Enum\Type::castValue($value, Enum\Type::STRING),
                    $arguments
                )
            ),
            'DIVIDE' => $query->divide(
                ...array_map(
                    fn ($value) => Enum\Type::castValue($value, Enum\Type::STRING),
                    $arguments
                )
            ),

            // string
            'BASE64_DECODE' => $query->fromBase64((string) ($arguments[0] ?? '')),
            'BASE64_ENCODE' => $query->toBase64((string) ($arguments[0] ?? '')),
            'CONCAT' => $query->concat(
                ...array_map(
                    fn ($value) => Enum\Type::castValue($value, Enum\Type::STRING),
                    $arguments
                )
            ),
            'CONCAT_WS' => $query->concatWithSeparator(
                (string) ($arguments[0] ?? ''),
                ...array_map(
                    fn ($value) => Enum\Type::castValue($value, Enum\Type::STRING),
                    array_slice($arguments, 1)
                )
            ),
            'EXPLODE' => $query->explode((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? ',')),
            'IMPLODE' => $query->implode((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? ',')),
            'LENGTH' => $query->length((string) ($arguments[0] ?? '')),
            'LOWER' => $query->lower((string) ($arguments[0] ?? '')),
            'RANDOM_STRING' => $query->randomString((int) ($arguments[0] ?? 10)),
            'REPLACE' => $query->replace(
                (string) ($arguments[0] ?? ''),
                (string) ($arguments[1] ?? ''),
                (string) ($arguments[2] ?? '')
            ),
            'REVERSE' => $query->reverse((string) ($arguments[0] ?? '')),
            'UPPER' => $query->upper((string) ($arguments[0] ?? '')),

            // utils
            'CAST' => $this->processCastFunction($query, $arguments),
            'COALESCE' => $query->coalesce(
                ...array_map(
                    fn ($value) => Enum\Type::castValue($value, Enum\Type::STRING),
                    $arguments
                )
            ),
            'COALESCE_NE' => $query->coalesceNotEmpty(
                ...array_map(
                    fn ($value) => Enum\Type::castValue($value, Enum\Type::STRING),
                    $arguments
                )
            ),
            'RANDOM_BYTES' => $query->randomBytes((int) ($arguments[0] ?? 10)),
            'UUID' => $query->uuid(),
            'LPAD' => $query->leftPad(
                (string) ($arguments[0] ?? ''),
                (int) ($arguments[1] ?? 0),
                (string) ($arguments[2] ?? ' ')
            ),
            'RPAD' => $query->rightPad(
                (string) ($arguments[0] ?? ''),
                (int) ($arguments[1] ?? 0),
                (string) ($arguments[2] ?? ' ')
            ),
            'ARRAY_COMBINE' => $query->arrayCombine((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? '')),
            'ARRAY_MERGE' => $query->arrayMerge((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? '')),
            'ARRAY_FILTER' => $query->arrayFilter((string) ($arguments[0] ?? '')),
            'ARRAY_SEARCH' => $query->arraySearch((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? '')),
            'COL_SPLIT' => $query->colSplit(
                (string) ($arguments[0] ?? ''),
                array_key_exists(1, $arguments) ? (string) $arguments[1] : null,
                array_key_exists(2, $arguments) ? (string) $arguments[2] : null
            ),
            'CURDATE' => $query->currentDate((bool) ($arguments[0] ?? 0)),
            'CURTIME' => $query->currentTime((bool) ($arguments[0] ?? 0)),
            'CURRENT_TIMESTAMP' => $query->currentTimestamp(),
            'NOW' => $query->now((bool) ($arguments[0] ?? 0)),
            'DATE_FORMAT' => $query->formatDate((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? 'c')),
            'FROM_UNIXTIME' => $query->fromUnixTime((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? 'c')),
            'STR_TO_DATE' => $query->strToDate((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? '')),
            'DATE_DIFF' => $query->dateDiff((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? '')),
            'DATE_ADD' => $query->dateAdd((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? '')),
            'DATE_SUB' => $query->dateSub((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? '')),
            'YEAR' => $query->year((string) ($arguments[0] ?? '')),
            'MONTH' => $query->month((string) ($arguments[0] ?? '')),
            'DAY' => $query->day((string) ($arguments[0] ?? '')),
            'MATCH' => $this->processMatchFunction($query, $arguments),
            'IF' => $query->if(
                (string) ($arguments[0] ?? ''),
                (string) ($arguments[1] ?? ''),
                (string) ($arguments[2] ?? '')
            ),
            'IFNULL' => $query->ifNull((string) ($arguments[0] ?? ''), (string) ($arguments[1] ?? '')),
            'ISNULL' => $query->isNull((string) ($arguments[0] ?? '')),
            'SUBSTRING', 'SUBSTR' => $query->substring(
                (string) ($arguments[0] ?? ''),
                (int) ($arguments[1] ?? 0),
                isset($arguments[2]) ? (int) $arguments[2] : null
            ),
            'LOCATE' => $query->locate(
                (string) ($arguments[0] ?? ''),
                (string) ($arguments[1] ?? ''),
                isset($arguments[2]) ? (int) $arguments[2] : null
            ),
            default => throw new Exception\UnexpectedValueException("Unknown function: $functionName"),
        };
    }

    private function parseWhereConditions(Interface\Query $query): void
    {
        $query->addWhereConditions($this->parseConditionGroup(
            new WhereConditionGroup(),
            fn (string $token, int $depth): bool => $depth === 0 && $this->isNextControlledKeyword()
        ));
    }

    private function parseHavingConditions(Interface\Query $query): void
    {
        $query->addHavingConditions($this->parseConditionGroup(
            new HavingConditionGroup(),
            fn (string $token, int $depth): bool => $depth === 0 && $this->isNextControlledKeyword()
        ));
    }

    private function parseGroupBy(Interface\Query $query): void
    {
        while (!$this->isEOF() && !$this->isNextControlledKeyword()) {
            $field = $this->nextToken();
            if ($field === ',') {
                continue;
            }

            $query->groupBy($field);
        }
    }

    private function parseSort(Interface\Query $query): void
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
                default => false,
            };
            if ($direction === false) {
                $direction = Enum\Sort::ASC;
                $this->rewindToken();
            }

            if ($this->isBacktick($field)) {
                $field = $this->removeQuotes($field);
            }

            $query->orderBy($field, $direction);
        }
    }

    /**
     * @param array<scalar|null> $arguments
     */
    private function processMatchFunction(Interface\Query $query, array $arguments): void
    {
        $againstFunction = $this->getFunction($this->peekToken());
        if (mb_strtoupper($againstFunction) !== 'AGAINST') {
            throw new Exception\QueryLogicException('Expected AGAINST keyword');
        }

        $againstArguments = $this->getFunctionArguments($this->peekToken());
        if (count($againstArguments) !== 1) {
            throw new Exception\QueryLogicException('Unexpected number of arguments for AGAINST');
        }

        $fulltextArguments = $this->parseSearchMode((string) $againstArguments[0]);
        if ($fulltextArguments[0] === null) {
            throw new Exception\QueryLogicException('Empty search query');
        }

        $query->matchAgainst(
            array_map(fn ($value) => (string) $value, $arguments),
            $fulltextArguments[0],
            $fulltextArguments[1]
        );
        $this->nextToken();
    }

    /**
     * @param array<scalar|null> $arguments
     */
    private function processCastFunction(Interface\Query $query, array $arguments): void
    {
        $expression = (string) ($arguments[0] ?? '');
        if (!preg_match('/^(.+)\s+AS\s+(.+)$/i', trim($expression), $matches)) {
            throw new Exception\QueryLogicException('Invalid CAST syntax');
        }

        $field = trim($matches[1]);
        $typeString = trim($matches[2]);
        $query->cast($field, $this->parseCastType($typeString));
    }

    private function parseCastType(string $typeString): Enum\Type
    {
        $normalized = strtoupper(trim($typeString));
        return match ($normalized) {
            'DOUBLE', 'FLOAT', 'REAL' => Enum\Type::FLOAT,
            'INT', 'INTEGER', 'SIGNED', 'UNSIGNED' => Enum\Type::INTEGER,
            'DECIMAL', 'NUMERIC', 'NUMBER' => Enum\Type::NUMBER,
            'BOOLEAN', 'BOOL', 'TRUE', 'FALSE' => Enum\Type::BOOLEAN,
            'CHAR', 'VARCHAR', 'STRING', 'TEXT' => Enum\Type::STRING,
            'NULL' => Enum\Type::NULL,
            default => $this->castEnumType($typeString),
        };
    }

    private function castEnumType(string $typeString): Enum\Type
    {
        try {
            return Enum\Type::from(strtolower($typeString));
        } catch (\ValueError) {
            throw new Exception\QueryLogicException(sprintf('Unsupported CAST type: %s', $typeString));
        }
    }

    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    public function setBasePath(?string $basePath): void
    {
        $this->basePath = $basePath;
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    private function validateFileQueryPath(string $fileQueryString, bool $mustExist = true): Query\FileQuery
    {
        $fileQuery = new Query\FileQuery($fileQueryString);
        if ($this->basePath === null) {
            return $fileQuery;
        }

        return FileQueryPathValidator::validate($fileQuery, $this->basePath, $mustExist);
    }
}
