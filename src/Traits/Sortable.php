<?php

namespace FQL\Traits;

use FQL\Enum;
use FQL\Exceptions;
use FQL\Interfaces\Query;

trait Sortable
{
    /**
     * @var array<string, Enum\Sort> $orderings
     */
    private array $orderings = [];

    public function sortBy(string $field, ?Enum\Sort $type = null): Query
    {
        if (isset($this->orderings[$field])) {
            throw new Exceptions\OrderByException(sprintf('Field "%s" is already used for sorting.', $field));
        }

        $this->orderings[$field] = $type ?? Enum\Sort::ASC;
        return $this;
    }

    public function orderBy(string $field, ?Enum\Sort $type = null): Query
    {
        return $this->sortBy($field, $type);
    }

    public function asc(): Query
    {
        return $this->setLastSortType(Enum\Sort::ASC);
    }

    public function desc(): Query
    {
        return $this->setLastSortType(Enum\Sort::DESC);
    }

    public function natural(): Query
    {
        return $this->setLastSortType(Enum\Sort::NATSORT);
    }

    public function shuffle(): Query
    {
        return $this->setLastSortType(Enum\Sort::SHUFFLE);
    }

    public function clearOrderings(): Query
    {
        $this->orderings = [];
        return $this;
    }

    private function orderByToString(): string
    {
        if (empty($this->orderings)) {
            return '';
        }

        $orderings = array_map(
            fn($field, Enum\Sort $type) => sprintf('%s %s', trim($field), trim(strtoupper($type->value))),
            array_keys($this->orderings),
            $this->orderings
        );

        return PHP_EOL . sprintf('ORDER BY %s', implode(', ', $orderings));
    }

    private function setLastSortType(Enum\Sort $type): Query
    {
        $lastField = array_key_last($this->orderings);
        if ($lastField === null) {
            throw new Exceptions\OrderByException(
                sprintf('No field available to set sorting type "%s".', $type->value)
            );
        }

        $this->orderings[$lastField] = $type;
        return $this;
    }
}
