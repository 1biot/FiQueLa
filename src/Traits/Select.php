<?php

namespace UQL\Traits;

use UQL\Exceptions;
use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions;
use UQL\Query\Query;

/**
 * @codingStandardsIgnoreStart
 * @phpstan-type SelectedField array{originField: string, alias: bool, function: null|Functions\Core\BaseFunction|Functions\Core\AggregateFunction|Functions\Core\NoFieldFunction}
 * @codingStandardsIgnoreEnd
 * @phpstan-type SelectedFields array<string, SelectedField>
 */
trait Select
{
    /** @var SelectedFields $selectedFields */
    private array $selectedFields = [];

    public function selectAll(): Query
    {
        $this->select(Query::SELECT_ALL);
        return $this;
    }

    /**
     * @param string $fields
     * @return Query
     * @throws Exceptions\SelectException
     */
    public function select(string $fields): Query
    {
        $fields = array_map('trim', explode(',', $fields));
        foreach ($fields as $field) {
            if ($field === Query::SELECT_ALL) {
                $this->selectedFields = [];
                continue;
            }

            if (isset($this->selectedFields[$field])) {
                throw new Exceptions\SelectException(sprintf('Field "%s" already defined', $field));
            }

            $this->addField($field);
        }

        return $this;
    }

    /**
     * @param string $alias
     * @return Query
     * @throws Exceptions\AliasException
     */
    public function as(string $alias): Query
    {
        if ($alias === '') {
            throw new Exceptions\AliasException('Alias cannot be empty');
        }

        $select = array_key_last($this->selectedFields);
        if ($select === null) {
            throw new Exceptions\AliasException(
                sprintf('Cannot use alias "%s" without any selected field', $alias)
            );
        } elseif ($this->selectedFields[$select]['alias']) {
            throw new Exceptions\AliasException(
                sprintf('"%s" cannot be used for a field that is already aliased.', $alias)
            );
        } elseif (isset($this->selectedFields[$alias])) {
            throw new Exceptions\AliasException(sprintf('"%s" already defined', $alias));
        }

        $function = $this->selectedFields[$select]['function'] ?? null;
        unset($this->selectedFields[$select]);

        $this->addField($select, $alias, $function);
        return $this;
    }

    public function concat(string ...$fields): Query
    {
        return $this->addFieldFunction(new Functions\String\Concat(...$fields));
    }

    public function concatWithSeparator(string $separator, string ...$fields): Query
    {
        return $this->addFieldFunction(new Functions\String\ConcatWS($separator, ...$fields));
    }

    public function coalesce(string ...$fields): Query
    {
        return $this->addFieldFunction(new Functions\Utils\Coalesce(...$fields));
    }

    public function coalesceNotEmpty(string ...$fields): Query
    {
        return $this->addFieldFunction(new Functions\Utils\CoalesceNotEmpty(...$fields));
    }

    public function explode(string $field, string $separator = ','): Query
    {
        return $this->addFieldFunction(new Functions\String\Explode($field, $separator));
    }

    public function split(string $field, string $separator = ','): Query
    {
        return $this->explode($field, $separator);
    }

    public function implode(string $field, string $separator = ','): Query
    {
        return $this->addFieldFunction(new Functions\String\Implode($field, $separator));
    }

    public function glue(string $field, string $separator = ','): Query
    {
        return $this->implode($field, $separator);
    }

    public function sha1(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Hashing\Sha1($field));
    }

    public function md5(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Hashing\Md5($field));
    }

    public function lower(string $field): Query
    {
        return $this->addFieldFunction(new Functions\String\Lower($field));
    }

    public function upper(string $field): Query
    {
        return $this->addFieldFunction(new Functions\String\Upper($field));
    }

    public function round(string $field, int $precision = 0): Query
    {
        return $this->addFieldFunction(new Functions\Math\Round($field, $precision));
    }

    public function length(string $field): Query
    {
        return $this->addFieldFunction(new Functions\String\Length($field));
    }

    public function reverse(string $field): Query
    {
        return $this->addFieldFunction(new Functions\String\Reverse($field));
    }

    public function ceil(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Math\Ceil($field));
    }

    public function floor(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Math\Floor($field));
    }

    /**
     * @param string $field
     * @param int $divisor
     * @return Query
     * @throws Exceptions\SelectException
     */
    public function modulo(string $field, int $divisor): Query
    {
        try {
            return $this->addFieldFunction(new Functions\Math\Mod($field, $divisor));
        } catch (UnexpectedValueException $e) {
            throw new Exceptions\SelectException($e->getMessage());
        }
    }

    public function count(?string $field = null): Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Count($field));
    }

    public function sum(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Sum($field));
    }

    public function groupConcat(string $field, string $separator = ','): Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\GroupConcat($field, $separator));
    }

    public function min(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Min($field));
    }

    public function avg(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Avg($field));
    }

    public function max(string $field): Query
    {
        return $this->addFieldFunction(new Functions\Aggregate\Max($field));
    }

    public function randomString(int $length = 10): Query
    {
        return $this->addFieldFunction(new Functions\String\RandomString($length));
    }

    public function randomBytes(int $length = 10): Query
    {
        return $this->addFieldFunction(new Functions\Utils\RandomBytes($length));
    }

    public function fromBase64(string $field): Query
    {
        return $this->addFieldFunction(new Functions\String\Base64Decode($field));
    }

    public function toBase64(string $field): Query
    {
        return $this->addFieldFunction(new Functions\String\Base64Encode($field));
    }

    private function addFieldFunction(
        Functions\Core\BaseFunction|Functions\Core\AggregateFunction|Functions\Core\NoFieldFunction $function
    ): Query {
        $this->addField(
            (string) $function,
            function: $function
        );

        return $this;
    }

    private function addField(
        string $field,
        ?string $alias = null,
        null|Functions\Core\BaseFunction|Functions\Core\AggregateFunction|Functions\Core\NoFieldFunction $function = null
    ): Query {
        $this->selectedFields[$alias ?? $field] = [
            'originField' => $field,
            'alias' => $alias !== null,
            'function' => $function,
        ];

        return $this;
    }

    private function selectToString(): string
    {
        $return = Query::SELECT . ' ';
        if ($this->selectedFields === []) {
            return $return . Query::SELECT_ALL;
        }

        $count = count($this->selectedFields) - 1;
        $counter = 0;
        foreach ($this->selectedFields as $finalField => $fieldData) {
            $return .= PHP_EOL . "\t" . $fieldData['originField'];
            if ($fieldData['alias']) {
                $return .= ' ' . Query::AS . ' ' . $finalField;
            }

            if ($counter++ < $count) {
                $return .= ',';
            }
        }

        return trim($return);
    }
}
