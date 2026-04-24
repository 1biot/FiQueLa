<?php

namespace FQL\Traits;

use FQL\Enum;
use FQL\Exception;
use FQL\Functions;
use FQL\Functions\FunctionRegistry;
use FQL\Interface;
use FQL\Interface\Query;
use FQL\Sql;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Sql\Token\Position;

/**
 * Fluent SELECT builder.
 *
 * In the Expression-First model every fluent helper accepts an **SQL
 * expression string** and parses it through {@see Sql\Provider::parseExpression}.
 * Examples:
 *
 * ```php
 * $query->select('name');                 // plain column → ColumnReferenceNode
 * $query->select('upper(lower(name))');   // nested functions
 * $query->sum('price + vat');             // aggregate over expression
 * $query->if('price > 100', '"expensive"', '"cheap"');
 * ```
 *
 * All the typed helpers (`lower`, `round`, `concat`, …) are thin sugar around
 * parseExpression + FunctionCallNode — they exist because `$query->lower('x')`
 * reads more naturally than `$query->select('lower(x)')` for ad-hoc code.
 *
 * @codingStandardsIgnoreStart
 * @phpstan-type AggregateSpec array{class: class-string<\FQL\Functions\Core\AggregateFunction>, expression: ExpressionNode, options: array<string, mixed>}
 * @phpstan-type SelectedField array{
 *     originField: string,
 *     alias: bool,
 *     expression: null|ExpressionNode,
 *     aggregate: null|AggregateSpec
 * }
 * @codingStandardsIgnoreEnd
 * @phpstan-type SelectedFields array<string, SelectedField>
 */
trait Select
{
    private bool $distinct = false;

    /** @var SelectedFields $selectedFields */
    private array $selectedFields = [];

    /** @var string[] $excludedFields */
    private array $excludedFields = [];

    private ?Functions\Utils\CaseBuilder $caseBuilder = null;

    private bool $selectBlocked = false;

    public function blockSelect(): void
    {
        $this->selectBlocked = true;
    }

    public function isSelectEmpty(): bool
    {
        return $this->selectedFields === [];
    }

    public function selectAll(): Interface\Query
    {
        $this->select(Interface\Query::SELECT_ALL);
        return $this;
    }

    public function exclude(string ...$fields): Interface\Query
    {
        if ($this->selectBlocked) {
            throw new Exception\QueryLogicException('SELECT is not allowed in DESCRIBE mode');
        }

        $this->excludedFields = array_values(array_filter(array_merge(
            $this->excludedFields,
            Sql\Support\FieldListSplitter::split(...$fields)
        )));
        return $this;
    }

    /**
     * @throws Exception\SelectException
     */
    public function select(string ...$fields): Interface\Query
    {
        if ($this->selectBlocked) {
            throw new Exception\QueryLogicException('SELECT is not allowed in DESCRIBE mode');
        }

        foreach (Sql\Support\FieldListSplitter::split(...$fields) as $spec) {
            $parsed = Sql\Support\FieldListSplitter::splitAlias($spec);
            $field = $parsed['field'];
            $alias = $parsed['alias'];

            // SELECT_ALL / wildcard expansions keep their bespoke handling in
            // Results\Stream — don't feed them through the expression parser.
            if ($field === Interface\Query::SELECT_ALL || str_ends_with($field, '.*')) {
                $this->storeField($field, $alias, expression: null, aggregate: null);
                continue;
            }

            // Legacy path-extraction syntax (`categories[]->name`) is not valid
            // SQL and the parser would reject it. Fall back to storing as a
            // plain `ColumnReferenceNode` so the runtime's `accessNestedValue`
            // handles the traversal.
            if (str_contains($field, '[]') || str_contains($field, '->')) {
                $this->storeField($field, $alias, expression: null, aggregate: null);
                continue;
            }

            try {
                $node = Sql\Provider::parseExpression($field);
            } catch (\FQL\Sql\Parser\ParseException) {
                // Plain identifier that happens to collide with a SQL keyword
                // (e.g. field literally named `group`). Fall back to storing it
                // as a raw column reference so the runtime path-resolver takes
                // over.
                $this->storeField($field, $alias, expression: null, aggregate: null);
                continue;
            }
            if ($node instanceof FunctionCallNode && FunctionRegistry::isAggregate($node->name)) {
                $this->storeAggregate($node, $alias, $field);
            } else {
                $this->storeExpression($node, $alias, $field);
            }
        }

        return $this;
    }

    public function distinct(bool $distinct = true): Interface\Query
    {
        if ($this->selectBlocked) {
            throw new Exception\QueryLogicException('SELECT is not allowed in DESCRIBE mode');
        }

        $this->distinct = $distinct;
        return $this;
    }

    /**
     * @throws Exception\AliasException
     */
    private function asSelect(string $alias): void
    {
        if ($alias === '') {
            throw new Exception\AliasException('Alias cannot be empty');
        }

        $last = array_key_last($this->selectedFields);
        if ($last === null) {
            throw new Exception\AliasException(
                sprintf('Cannot use alias "%s" without any selected field', $alias)
            );
        } elseif ($this->selectedFields[$last]['alias']) {
            throw new Exception\AliasException(
                sprintf('"%s" cannot be used for a field that is already aliased.', $alias)
            );
        } elseif (isset($this->selectedFields[$alias])) {
            throw new Exception\AliasException(sprintf('"%s" already defined', $alias));
        }

        $data = $this->selectedFields[$last];
        unset($this->selectedFields[$last]);

        $this->selectedFields[$alias] = [
            'originField' => $data['originField'],
            'alias' => true,
            'expression' => $data['expression'],
            'aggregate' => $data['aggregate'],
        ];
    }

    /* -------------------------------------------------------------------- */
    /*  Scalar fluent helpers (all parse SQL expression strings)            */
    /* -------------------------------------------------------------------- */

    public function concat(string ...$fields): Interface\Query
    {
        return $this->wrapFunction('CONCAT', $fields);
    }

    public function concatWithSeparator(string $separator, string ...$fields): Interface\Query
    {
        $args = [$this->literal($separator)];
        foreach ($fields as $field) {
            $args[] = Sql\Provider::parseExpression($field);
        }
        return $this->addFunctionCall('CONCAT_WS', $args);
    }

    public function coalesce(string ...$fields): Interface\Query
    {
        return $this->wrapFunction('COALESCE', $fields);
    }

    public function coalesceNotEmpty(string ...$fields): Interface\Query
    {
        return $this->wrapFunction('COALESCE_NE', $fields);
    }

    public function explode(string $field, string $separator = ','): Interface\Query
    {
        return $this->addFunctionCall('EXPLODE', [
            Sql\Provider::parseExpression($field),
            $this->literal($separator),
        ]);
    }

    public function split(string $field, string $separator = ','): Interface\Query
    {
        return $this->explode($field, $separator);
    }

    public function implode(string $field, string $separator = ','): Interface\Query
    {
        return $this->addFunctionCall('IMPLODE', [
            Sql\Provider::parseExpression($field),
            $this->literal($separator),
        ]);
    }

    public function glue(string $field, string $separator = ','): Interface\Query
    {
        return $this->implode($field, $separator);
    }

    public function sha1(string $field): Interface\Query
    {
        return $this->wrapFunction('SHA1', [$field]);
    }

    public function md5(string $field): Interface\Query
    {
        return $this->wrapFunction('MD5', [$field]);
    }

    public function lower(string $field): Interface\Query
    {
        return $this->wrapFunction('LOWER', [$field]);
    }

    public function upper(string $field): Interface\Query
    {
        return $this->wrapFunction('UPPER', [$field]);
    }

    public function round(string $field, int $precision = 0): Interface\Query
    {
        return $this->addFunctionCall('ROUND', [
            Sql\Provider::parseExpression($field),
            $this->literal($precision),
        ]);
    }

    public function length(string $field): Interface\Query
    {
        return $this->wrapFunction('LENGTH', [$field]);
    }

    public function reverse(string $field): Interface\Query
    {
        return $this->wrapFunction('REVERSE', [$field]);
    }

    public function ceil(string $field): Interface\Query
    {
        return $this->wrapFunction('CEIL', [$field]);
    }

    public function floor(string $field): Interface\Query
    {
        return $this->wrapFunction('FLOOR', [$field]);
    }

    /**
     * @throws Exception\SelectException
     */
    public function modulo(string $field, int $divisor): Interface\Query
    {
        return $this->addFunctionCall('MOD', [
            Sql\Provider::parseExpression($field),
            $this->literal($divisor),
        ]);
    }

    public function add(string ...$fields): Interface\Query
    {
        return $this->wrapFunction('ADD', $fields);
    }

    public function subtract(string ...$fields): Interface\Query
    {
        return $this->wrapFunction('SUB', $fields);
    }

    public function multiply(string ...$fields): Interface\Query
    {
        return $this->wrapFunction('MULTIPLY', $fields);
    }

    public function divide(string ...$fields): Interface\Query
    {
        return $this->wrapFunction('DIVIDE', $fields);
    }

    /* -------------------------------------------------------------------- */
    /*  Aggregate fluent helpers                                            */
    /* -------------------------------------------------------------------- */

    public function count(?string $field = null, bool $distinct = false): Interface\Query
    {
        $expression = $field === null || $field === '' || $field === Interface\Query::SELECT_ALL
            ? new \FQL\Sql\Ast\Expression\StarNode(Position::synthetic())
            : Sql\Provider::parseExpression($field);
        return $this->storeAggregate(
            new FunctionCallNode('COUNT', [$expression], $distinct, Position::synthetic()),
            null,
            null
        );
    }

    public function sum(string $field, bool $distinct = false): Interface\Query
    {
        return $this->storeAggregate(
            new FunctionCallNode(
                'SUM',
                [Sql\Provider::parseExpression($field)],
                $distinct,
                Position::synthetic()
            ),
            null,
            null
        );
    }

    public function groupConcat(string $field, string $separator = ',', bool $distinct = false): Interface\Query
    {
        return $this->storeAggregate(
            new FunctionCallNode(
                'GROUP_CONCAT',
                [Sql\Provider::parseExpression($field), $this->literal($separator)],
                $distinct,
                Position::synthetic()
            ),
            null,
            null
        );
    }

    public function min(string $field, bool $distinct = false): Interface\Query
    {
        return $this->storeAggregate(
            new FunctionCallNode(
                'MIN',
                [Sql\Provider::parseExpression($field)],
                $distinct,
                Position::synthetic()
            ),
            null,
            null
        );
    }

    public function avg(string $field): Interface\Query
    {
        return $this->storeAggregate(
            new FunctionCallNode(
                'AVG',
                [Sql\Provider::parseExpression($field)],
                false,
                Position::synthetic()
            ),
            null,
            null
        );
    }

    public function max(string $field, bool $distinct = false): Interface\Query
    {
        return $this->storeAggregate(
            new FunctionCallNode(
                'MAX',
                [Sql\Provider::parseExpression($field)],
                $distinct,
                Position::synthetic()
            ),
            null,
            null
        );
    }

    /* -------------------------------------------------------------------- */
    /*  Misc helpers                                                        */
    /* -------------------------------------------------------------------- */

    public function randomString(int $length = 10): Interface\Query
    {
        return $this->addFunctionCall('RANDOM_STRING', [$this->literal($length)]);
    }

    public function randomBytes(int $length = 10): Interface\Query
    {
        return $this->addFunctionCall('RANDOM_BYTES', [$this->literal($length)]);
    }

    public function uuid(): Interface\Query
    {
        return $this->addFunctionCall('UUID', []);
    }

    public function fromBase64(string $field): Interface\Query
    {
        return $this->wrapFunction('BASE64_DECODE', [$field]);
    }

    public function toBase64(string $field): Interface\Query
    {
        return $this->wrapFunction('BASE64_ENCODE', [$field]);
    }

    /**
     * @param string[] $fields
     */
    public function fulltext(array $fields, string $searchQuery): Interface\Query
    {
        return $this->matchAgainst($fields, $searchQuery);
    }

    /**
     * @param string[] $fields
     */
    public function matchAgainst(array $fields, string $searchQuery, ?Enum\Fulltext $mode = null): Interface\Query
    {
        $node = new \FQL\Sql\Ast\Expression\MatchAgainstNode(
            array_map(
                static fn (string $f): ColumnReferenceNode
                    => new ColumnReferenceNode($f, Position::synthetic()),
                $fields
            ),
            $searchQuery,
            $mode ?? Enum\Fulltext::NATURAL,
            Position::synthetic()
        );
        return $this->storeExpression($node, null, null);
    }

    public function leftPad(string $field, int $length, string $padString = ' '): Interface\Query
    {
        return $this->addFunctionCall('LPAD', [
            Sql\Provider::parseExpression($field),
            $this->literal($length),
            $this->literal($padString),
        ]);
    }

    public function rightPad(string $field, int $length, string $padString = ' '): Interface\Query
    {
        return $this->addFunctionCall('RPAD', [
            Sql\Provider::parseExpression($field),
            $this->literal($length),
            $this->literal($padString),
        ]);
    }

    public function replace(string $field, string $search, string $replace): Interface\Query
    {
        return $this->addFunctionCall('REPLACE', [
            Sql\Provider::parseExpression($field),
            $this->literal($search),
            $this->literal($replace),
        ]);
    }

    public function arrayCombine(string $keysArrayField, string $valueArrayField): Interface\Query
    {
        return $this->wrapFunction('ARRAY_COMBINE', [$keysArrayField, $valueArrayField]);
    }

    public function arrayMerge(string $arrayField, string $arrayField2): Interface\Query
    {
        return $this->wrapFunction('ARRAY_MERGE', [$arrayField, $arrayField2]);
    }

    public function colSplit(string $field, ?string $format = null, ?string $keyField = null): Interface\Query
    {
        return $this->addFunctionCall('COL_SPLIT', [
            Sql\Provider::parseExpression($field),
            $format === null ? $this->literal(null) : $this->literal($format),
            $keyField === null ? $this->literal(null) : $this->literal($keyField),
        ]);
    }

    public function arrayFilter(string $field): Query
    {
        return $this->wrapFunction('ARRAY_FILTER', [$field]);
    }

    public function arraySearch(string $field, string $value): Query
    {
        return $this->addFunctionCall('ARRAY_SEARCH', [
            Sql\Provider::parseExpression($field),
            $this->literal($value),
        ]);
    }

    public function cast(string $field, Enum\Type $as): Interface\Query
    {
        $node = new \FQL\Sql\Ast\Expression\CastExpressionNode(
            Sql\Provider::parseExpression($field),
            $as,
            Position::synthetic()
        );
        return $this->storeExpression($node, null, null);
    }

    public function strToDate(string $valueField, string $format): Interface\Query
    {
        return $this->addFunctionCall('STR_TO_DATE', [
            Sql\Provider::parseExpression($valueField),
            $this->literal($format),
        ]);
    }

    public function formatDate(string $dateField, string $format = 'c'): Interface\Query
    {
        return $this->addFunctionCall('DATE_FORMAT', [
            Sql\Provider::parseExpression($dateField),
            $this->literal($format),
        ]);
    }

    public function fromUnixTime(string $dateField, string $format = 'c'): Interface\Query
    {
        return $this->addFunctionCall('FROM_UNIXTIME', [
            Sql\Provider::parseExpression($dateField),
            $this->literal($format),
        ]);
    }

    public function currentDate(bool $numeric = false): Interface\Query
    {
        return $this->addFunctionCall('CURDATE', [$this->literal($numeric)]);
    }

    public function currentTime(bool $numeric = false): Interface\Query
    {
        return $this->addFunctionCall('CURTIME', [$this->literal($numeric)]);
    }

    public function currentTimestamp(): Interface\Query
    {
        return $this->addFunctionCall('CURRENT_TIMESTAMP', []);
    }

    public function now(bool $numeric = false): Interface\Query
    {
        return $this->addFunctionCall('NOW', [$this->literal($numeric)]);
    }

    public function dateDiff(string $dateField, string $dateField2): Interface\Query
    {
        return $this->wrapFunction('DATE_DIFF', [$dateField, $dateField2]);
    }

    public function dateAdd(string $dateField, string $interval): Interface\Query
    {
        return $this->addFunctionCall('DATE_ADD', [
            Sql\Provider::parseExpression($dateField),
            $this->literal($interval),
        ]);
    }

    public function dateSub(string $dateField, string $interval): Interface\Query
    {
        return $this->addFunctionCall('DATE_SUB', [
            Sql\Provider::parseExpression($dateField),
            $this->literal($interval),
        ]);
    }

    public function year(string $dateField): Interface\Query
    {
        return $this->wrapFunction('YEAR', [$dateField]);
    }

    public function month(string $dateField): Interface\Query
    {
        return $this->wrapFunction('MONTH', [$dateField]);
    }

    public function day(string $dateField): Interface\Query
    {
        return $this->wrapFunction('DAY', [$dateField]);
    }

    public function if(string $conditionString, string $trueStatement, string $falseStatement): Interface\Query
    {
        return $this->addFunctionCall('IF', [
            Sql\Provider::parseExpression($conditionString),
            Sql\Provider::parseExpression($trueStatement),
            Sql\Provider::parseExpression($falseStatement),
        ]);
    }

    public function ifNull(string $field, string $trueStatement): Query
    {
        return $this->addFunctionCall('IFNULL', [
            Sql\Provider::parseExpression($field),
            Sql\Provider::parseExpression($trueStatement),
        ]);
    }

    public function isNull(string $field): Query
    {
        return $this->wrapFunction('ISNULL', [$field]);
    }

    public function case(): Query
    {
        $this->caseBuilder = new Functions\Utils\CaseBuilder();
        return $this;
    }

    public function whenCase(string $conditionString, string $thenStatement): Query
    {
        if ($this->caseBuilder === null) {
            throw new Exception\CaseException('First create a CASE statement for using WHEN statement.');
        }

        $this->caseBuilder->when(
            Sql\Provider::parseCondition($conditionString),
            Sql\Provider::parseExpression($thenStatement)
        );
        return $this;
    }

    public function elseCase(string $defaultCaseStatement): Query
    {
        if ($this->caseBuilder === null) {
            throw new Exception\CaseException('First create a CASE statement for using ELSE statement.');
        }

        if (!$this->caseBuilder->hasWhens()) {
            throw new Exception\CaseException('First add a WHEN statement.');
        }

        if ($this->caseBuilder->hasElse()) {
            throw new Exception\CaseException('CASE statement already has a default statement.');
        }

        $this->caseBuilder->setElse(Sql\Provider::parseExpression($defaultCaseStatement));
        return $this;
    }

    public function endCase(): Query
    {
        if ($this->caseBuilder === null) {
            throw new Exception\CaseException('First create a CASE statement for using END CASE.');
        }

        $node = $this->caseBuilder->build();
        $this->caseBuilder = null;
        return $this->storeExpression($node, null, null);
    }

    public function substring(string $field, int $start, ?int $length = null): Query
    {
        $args = [
            Sql\Provider::parseExpression($field),
            $this->literal($start),
        ];
        if ($length !== null) {
            $args[] = $this->literal($length);
        }
        return $this->addFunctionCall('SUBSTRING', $args);
    }

    public function locate(string $substring, string $field, ?int $position = null): Query
    {
        $args = [
            $this->literal($substring),
            Sql\Provider::parseExpression($field),
        ];
        if ($position !== null) {
            $args[] = $this->literal($position);
        }
        return $this->addFunctionCall('LOCATE', $args);
    }

    /* -------------------------------------------------------------------- */
    /*  Internal helpers (not in Interface\Query)                           */
    /* -------------------------------------------------------------------- */

    /**
     * Builds a `FunctionCallNode` where every argument is a string column /
     * expression parsed via `parseExpression()`. Convenience wrapper for
     * helpers that expose field-only arg lists (e.g. `concat`, `add`).
     *
     * @param string[] $fieldExpressions
     */
    private function wrapFunction(string $name, array $fieldExpressions): Interface\Query
    {
        $args = array_map(
            static fn (string $f): ExpressionNode => Sql\Provider::parseExpression($f),
            $fieldExpressions
        );
        return $this->addFunctionCall($name, $args);
    }

    /**
     * @param list<ExpressionNode> $args
     */
    private function addFunctionCall(string $name, array $args): Interface\Query
    {
        $node = new FunctionCallNode($name, $args, false, Position::synthetic());
        if (FunctionRegistry::isAggregate($name)) {
            return $this->storeAggregate($node, null, null);
        }
        return $this->storeExpression($node, null, null);
    }

    /**
     * Produces a literal AST node from a PHP scalar. Used by helpers that
     * accept non-field arguments (e.g. separator strings, precision integers).
     */
    private function literal(mixed $value): LiteralNode
    {
        $type = match (true) {
            is_int($value) => Enum\Type::INTEGER,
            is_float($value) => Enum\Type::FLOAT,
            is_bool($value) => Enum\Type::BOOLEAN,
            $value === null => Enum\Type::NULL,
            default => Enum\Type::STRING,
        };
        $raw = $value === null ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        return new LiteralNode($value, $type, $raw, Position::synthetic());
    }

    /**
     * Registers a non-aggregate expression as a SELECT field.
     *
     * @param string|null $originField raw field spec from the user (for SELECT debug)
     */
    private function storeExpression(
        ExpressionNode $expression,
        ?string $alias,
        ?string $originField
    ): Interface\Query {
        if ($this->selectBlocked) {
            throw new Exception\QueryLogicException('SELECT is not allowed in DESCRIBE mode');
        }
        $compiler = new ExpressionCompiler();
        $rendered = $originField ?? $compiler->renderExpression($expression);
        $this->storeField($rendered, $alias, expression: $expression, aggregate: null);
        return $this;
    }

    /**
     * Registers an aggregate `FunctionCallNode` against the provided options.
     * The runtime grouping phase consumes the resulting `AggregateSpec` through
     * {@see \FQL\Results\Stream}.
     *
     * @param string|null $originField raw field spec from the user (for SELECT debug)
     */
    private function storeAggregate(
        FunctionCallNode $call,
        ?string $alias,
        ?string $originField
    ): Interface\Query {
        if ($this->selectBlocked) {
            throw new Exception\QueryLogicException('SELECT is not allowed in DESCRIBE mode');
        }

        $name = strtoupper($call->name);
        $class = FunctionRegistry::getAggregate($name);
        if ($class === null) {
            throw new Exception\UnexpectedValueException(
                sprintf('Aggregate function "%s" is not registered', $name)
            );
        }

        $args = $call->arguments;
        $expression = $args[0] ?? new \FQL\Sql\Ast\Expression\StarNode(Position::synthetic());

        // Reject nonsensical COUNT(DISTINCT *) early — the Count aggregate treats
        // the star value as identical across rows, which would always return 1.
        if (
            $name === 'COUNT'
            && $call->distinct
            && $expression instanceof \FQL\Sql\Ast\Expression\StarNode
        ) {
            throw new Exception\InvalidArgumentException('DISTINCT is not supported with COUNT(*)');
        }

        $options = ['distinct' => $call->distinct];
        if ($name === 'GROUP_CONCAT' && isset($args[1]) && $args[1] instanceof LiteralNode) {
            $options['separator'] = (string) $args[1]->value;
        }

        /** @var AggregateSpec $spec */
        $spec = [
            'class' => $class,
            'expression' => $expression,
            'options' => $options,
        ];

        $compiler = new ExpressionCompiler();
        $rendered = $originField ?? $compiler->renderExpression($call);
        $this->storeField($rendered, $alias, expression: null, aggregate: $spec);
        return $this;
    }

    /**
     * Low-level writer into `$selectedFields`. Collapses the four legacy code
     * paths (function instance / expression / aggregate / plain column) into
     * one schema: `{originField, alias, expression, aggregate}`.
     *
     * Plain column references passed via `select('name')` are auto-wrapped
     * into a `ColumnReferenceNode` so the runtime evaluator always has an AST
     * to walk. SELECT_ALL (`*`) and wildcard expansions (`foo.*`) keep their
     * specialised handling in `Results\Stream::applySelect`.
     *
     * @param AggregateSpec|null $aggregate
     * @throws Exception\SelectException
     */
    private function storeField(
        string $field,
        ?string $alias,
        ?ExpressionNode $expression,
        ?array $aggregate
    ): void {
        $key = $alias ?? $field;

        if (isset($this->selectedFields[$key])) {
            throw new Exception\SelectException(sprintf('Field "%s" already defined', $key));
        }

        if (
            $expression === null
            && $aggregate === null
            && $field !== Interface\Query::SELECT_ALL
            && !str_ends_with($field, '.*')
        ) {
            $expression = new ColumnReferenceNode($field, Position::synthetic());
        }

        $this->selectedFields[$key] = [
            'originField' => $field,
            'alias' => $alias !== null,
            'expression' => $expression,
            'aggregate' => $aggregate,
        ];
    }

    private function selectToString(): string
    {
        $return = Interface\Query::SELECT;
        if ($this->distinct) {
            $return .= ' ' . Interface\Query::DISTINCT;
        }

        $fields = [];
        if ($this->selectedFields === []) {
            $fields[] = Interface\Query::SELECT_ALL;
        }

        foreach ($this->selectedFields as $finalField => $fieldData) {
            $field = $fieldData['originField'];
            if ($fieldData['alias']) {
                $field .= ' ' . Interface\Query::AS . ' ' . $finalField;
            }

            $fields[] = $field;
        }

        $count = count($fields) - 1;
        $counter = 0;
        foreach ($fields as $field) {
            $return .= PHP_EOL . "\t" . $field;
            if ($counter++ < $count) {
                $return .= ',';
            }
        }

        if ($this->excludedFields !== []) {
            $return .= PHP_EOL . Interface\Query::EXCLUDE;
            $count = count($this->excludedFields) - 1;
            $counter = 0;
            foreach ($this->excludedFields as $field) {
                $return .= ($count ? PHP_EOL . "\t" : ' ') . $field;
                if ($counter++ < $count) {
                    $return .= ',';
                }
            }
        }

        return trim($return);
    }
}
