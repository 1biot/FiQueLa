<?php

namespace FQL\Traits;

use FQL\Enum;
use FQL\Exception;
use FQL\Functions;
use FQL\Functions\Core;
use FQL\Interface;
use FQL\Interface\Query;
use FQL\Sql;

/**
 * @codingStandardsIgnoreStart
 * @phpstan-type SelectedField array{
 *     originField: string,
 *     alias: bool,
 *     function: null|Core\BaseFunction|Core\AggregateFunction|Core\NoFieldFunction
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

    private ?Functions\Utils\SelectCase $case = null;

    public function selectAll(): Interface\Query
    {
        $this->select(Interface\Query::SELECT_ALL);
        return $this;
    }

    public function exclude(string ...$fields): Interface\Query
    {
        $fqlTokenizer = new Sql\SqlLexer();
        $fields = $fqlTokenizer->tokenize(implode(',', $fields));
        $this->excludedFields = array_filter(array_merge(
            $this->excludedFields,
            array_filter(array_map('trim', $fields))
        ));
        return $this;
    }

    /**
     * @param string ...$fields
     * @return Interface\Query
     * @throws Exception\SelectException
     */
    public function select(string ...$fields): Interface\Query
    {
        $fqlTokenizer = new Sql\SqlLexer();
        $fields = $fqlTokenizer->tokenize(implode(',', $fields));
        foreach ($fields as $field) {
            if ($field === Interface\Query::SELECT_ALL) {
                $this->selectedFields = [];
                continue;
            }

            if (isset($this->selectedFields[$field])) {
                throw new Exception\SelectException(sprintf('Field "%s" already defined', $field));
            }

            $this->addField($field);
        }

        return $this;
    }

    public function distinct(bool $distinct = true): Interface\Query
    {
        $this->distinct = $distinct;
        return $this;
    }

    /**
     * @param string $alias
     * @return Interface\Query
     * @throws Exception\AliasException
     */
    public function as(string $alias): Interface\Query
    {
        if ($alias === '') {
            throw new Exception\AliasException('Alias cannot be empty');
        }

        $select = array_key_last($this->selectedFields);
        if ($select === null) {
            throw new Exception\AliasException(
                sprintf('Cannot use alias "%s" without any selected field', $alias)
            );
        } elseif ($this->selectedFields[$select]['alias']) {
            throw new Exception\AliasException(
                sprintf('"%s" cannot be used for a field that is already aliased.', $alias)
            );
        } elseif (isset($this->selectedFields[$alias])) {
            throw new Exception\AliasException(sprintf('"%s" already defined', $alias));
        }

        $function = $this->selectedFields[$select]['function'] ?? null;
        unset($this->selectedFields[$select]);

        $this->addField($select, $alias, $function);
        return $this;
    }

    public function custom(
        Core\MultipleFieldsFunction|Core\NoFieldFunction|Core\SingleFieldFunction $function
    ): Interface\Query {
        return $this->addFieldFunction($function);
    }

    public function concat(string ...$fields): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Concat(...$fields));
    }

    public function concatWithSeparator(string $separator, string ...$fields): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\ConcatWS($separator, ...$fields));
    }

    public function coalesce(string ...$fields): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\Coalesce(...$fields));
    }

    public function coalesceNotEmpty(string ...$fields): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\CoalesceNotEmpty(...$fields));
    }

    public function explode(string $field, string $separator = ','): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Explode($field, $separator));
    }

    public function split(string $field, string $separator = ','): Interface\Query
    {
        return $this->explode($field, $separator);
    }

    public function implode(string $field, string $separator = ','): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Implode($field, $separator));
    }

    public function glue(string $field, string $separator = ','): Interface\Query
    {
        return $this->implode($field, $separator);
    }

    public function sha1(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Hashing\Sha1($field));
    }

    public function md5(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Hashing\Md5($field));
    }

    public function lower(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Lower($field));
    }

    public function upper(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Upper($field));
    }

    public function round(string $field, int $precision = 0): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Math\Round($field, $precision));
    }

    public function length(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\Length($field));
    }

    public function reverse(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Reverse($field));
    }

    public function ceil(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Math\Ceil($field));
    }

    public function floor(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Math\Floor($field));
    }

    /**
     * @param string $field
     * @param int $divisor
     * @return Interface\Query
     * @throws Exception\SelectException
     */
    public function modulo(string $field, int $divisor): Interface\Query
    {
        try {
            return $this->addFieldFunction(new Functions\Math\Mod($field, $divisor));
        } catch (Exception\UnexpectedValueException $e) {
            throw new Exception\SelectException($e->getMessage());
        }
    }

    public function count(?string $field = null): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Count($field));
    }

    public function sum(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Sum($field));
    }

    public function groupConcat(string $field, string $separator = ','): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\GroupConcat($field, $separator));
    }

    public function min(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Min($field));
    }

    public function avg(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Avg($field));
    }

    public function max(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Max($field));
    }

    public function randomString(int $length = 10): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\RandomString($length));
    }

    public function randomBytes(int $length = 10): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\RandomBytes($length));
    }

    public function fromBase64(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Base64Decode($field));
    }

    public function toBase64(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Base64Encode($field));
    }

    /**
     * @param string[] $fields
     */
    public function fulltext(array $fields, string $searchQuery): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Fulltext($fields, $searchQuery));
    }

    /**
     * @param string[] $fields
     */
    public function matchAgainst(array $fields, string $searchQuery, ?Enum\Fulltext $mode = null): Interface\Query
    {
        return $this->fulltext($fields, $searchQuery);
    }

    public function arrayCombine(string $keysArrayField, string $valueArrayField): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\ArrayCombine($keysArrayField, $valueArrayField));
    }

    public function arrayMerge(string $arrayField, string $arrayField2): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\ArrayMerge($arrayField, $arrayField2));
    }

    public function arrayFilter(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Utils\ArrayFilter($field));
    }

    public function formatDate(string $dateField, string $format = 'c'): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\DateFormat($dateField, $format));
    }

    public function if(string $conditionString, string $trueStatement, string $falseStatement): Interface\Query
    {
        return $this->addFieldFunction(
            new Functions\Utils\SelectIf($conditionString, $trueStatement, $falseStatement)
        );
    }

    public function ifNull(string $field, string $trueStatement): Query
    {
        return $this->addFieldFunction(
            new Functions\Utils\SelectIfNull($field, $trueStatement)
        );
    }

    public function isNull(string $field): Query
    {
        return $this->addFieldFunction(
            new Functions\Utils\SelectIsNull($field)
        );
    }

    public function case(): Query
    {
        $this->case = new Functions\Utils\SelectCase();
        return $this;
    }

    public function whenCase(string $conditionString, string $thenStatement): Query
    {
        if ($this->case === null) {
            throw new Exception\CaseException('First create a CASE statement for using WHEN statement.');
        }

        $this->case->addCondition($conditionString, $thenStatement);
        return $this;
    }

    public function elseCase(string $defaultCaseStatement): Query
    {
        if ($this->case === null) {
            throw new Exception\CaseException('First create a CASE statement for using ELSE statement.');
        }

        if (!$this->case->hasConditions()) {
            throw new Exception\CaseException('First add a WHEN statement.');
        }

        if ($this->case->hasDefaultStatement()) {
            throw new Exception\CaseException('CASE statement already has a default statement.');
        }

        $this->case->addDefault($defaultCaseStatement);
        return $this;
    }

    public function endCase(): Query
    {
        return $this->addFieldFunction($this->case);
    }

    private function addFieldFunction(
        Core\BaseFunction|Core\AggregateFunction|Core\NoFieldFunction $function
    ): Interface\Query {
        $this->addField(
            (string) $function,
            function: $function
        );

        return $this;
    }

    private function addField(
        string $field,
        ?string $alias = null,
        null|Core\BaseFunction|Core\AggregateFunction|Core\NoFieldFunction $function = null
    ): Interface\Query {
        $this->selectedFields[$alias ?? $field] = [
            'originField' => $field,
            'alias' => $alias !== null,
            'function' => $function,
        ];

        return $this;
    }

    private function selectToString(): string
    {
        $return = Interface\Query::SELECT;
        if ($this->distinct) {
            $return .= ' ' . Interface\Query::DISTINCT;
        }

        if ($this->selectedFields === []) {
            $return .= ' ' . Interface\Query::SELECT_ALL;
        }

        $count = count($this->selectedFields) - 1;
        $counter = 0;
        foreach ($this->selectedFields as $finalField => $fieldData) {
            $return .= PHP_EOL . "\t" . $fieldData['originField'];
            if ($fieldData['alias']) {
                $return .= ' ' . Interface\Query::AS . ' ' . $finalField;
            }

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
