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
 *     function: null|Core\BaseFunction|Core\AggregateFunction|Core\NoFieldFunction|Core\BaseFunctionByReference
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

    public function add(string|float|int ...$fields): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Math\Add(...$fields));
    }

    public function subtract(string|float|int ...$fields): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Math\Sub(...$fields));
    }

    public function multiply(string|float|int ...$fields): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Math\Multiply(...$fields));
    }

    public function divide(string|float|int ...$fields): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Math\Divide(...$fields));
    }

    public function count(?string $field = null, bool $distinct = false): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Count($field, $distinct));
    }

    public function sum(string $field, bool $distinct = false): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Sum($field, $distinct));
    }

    public function groupConcat(string $field, string $separator = ',', bool $distinct = false): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\GroupConcat($field, $separator, $distinct));
    }

    public function min(string $field, bool $distinct = false): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Min($field, $distinct));
    }

    public function avg(string $field): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Avg($field));
    }

    public function max(string $field, bool $distinct = false): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Max($field, $distinct));
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

    public function leftPad(string $field, int $length, string $padString = ' '): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\LeftPad($field, $length, $padString));
    }

    public function rightPad(string $field, int $length, string $padString = ' '): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\RightPad($field, $length, $padString));
    }

    public function replace(string $field, string $search, string $replace): Interface\Query
    {
        return $this->addFieldFunction(new Functions\String\Replace($field, $search, $replace));
    }

    public function arrayCombine(string $keysArrayField, string $valueArrayField): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\ArrayCombine($keysArrayField, $valueArrayField));
    }

    public function arrayMerge(string $arrayField, string $arrayField2): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\ArrayMerge($arrayField, $arrayField2));
    }

    public function colSplit(string $field, ?string $format = null, ?string $keyField = null): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\ColSplit($field, $format, $keyField));
    }

    public function arrayFilter(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Utils\ArrayFilter($field));
    }

    public function cast(string $field, Enum\Type $as): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\Cast($field, $as));
    }

    public function strToDate(string $valueField, string $format): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\StrToDate($valueField, $format));
    }

    public function formatDate(string $dateField, string $format = 'c'): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\DateFormat($dateField, $format));
    }

    public function fromUnixTime(string $dateField, string $format = 'c'): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\FromUnixTime($dateField, $format));
    }

    public function currentDate(bool $numeric = false): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\CurrentDate($numeric));
    }

    public function currentTime(bool $numeric = false): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\CurrentTime($numeric));
    }

    public function currentTimestamp(): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\CurrentTimestamp());
    }

    public function now(bool $numeric = false): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\Now($numeric));
    }

    public function dateDiff(string $dateField, string $dateField2): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\DateDiff($dateField, $dateField2));
    }

    public function dateAdd(string $dateField, string $interval): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\DateAdd($dateField, $interval));
    }

    public function dateSub(string $dateField, string $interval): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\DateSub($dateField, $interval));
    }

    public function year(string $dateField): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\Year($dateField));
    }

    public function month(string $dateField): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\Month($dateField));
    }

    public function day(string $dateField): Interface\Query
    {
        return $this->addFieldFunction(new Functions\Utils\Day($dateField));
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
        if ($this->case === null) {
            throw new Exception\CaseException('First create a CASE statement for using END CASE.');
        }

        $case = $this->case;
        $this->case = null;
        $this->addFieldFunction($case);
        return $this;
    }

    public function substring(string $field, int $start, ?int $length = null): Query
    {
        return $this->addFieldFunction(new Functions\String\Substring($field, $start, $length));
    }

    public function locate(string $substring, string $field, ?int $position = null): Query
    {
        return $this->addFieldFunction(new Functions\String\Locate($substring, $field, $position));
    }

    private function addFieldFunction(
        Core\BaseFunction|Core\AggregateFunction|Core\NoFieldFunction|Core\BaseFunctionByReference $function
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
        null|Core\BaseFunction|Core\AggregateFunction|Core\NoFieldFunction|Core\BaseFunctionByReference $function = null
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
