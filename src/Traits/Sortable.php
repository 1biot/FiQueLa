<?php

namespace UQL\Traits;

use UQL\Enum\Sort;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Query\Provider;
use UQL\Query\Query;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from Provider
*/
trait Sortable
{
    /**
     * @var array<string, Sort> $orderings
     */
    private array $orderings = [];

    public function sortBy(string $field, ?Sort $type = null): Query
    {
        if (isset($this->orderings[$field])) {
            throw new InvalidArgumentException(sprintf('Field "%s" is already used for sorting.', $field));
        }

        $this->orderings[$field] = $type ?? Sort::ASC;
        return $this;
    }

    public function orderBy(string $field, ?Sort $type = null): Query
    {
        return $this->sortBy($field, $type);
    }

    public function asc(): Query
    {
        return $this->setLastSortType(Sort::ASC);
    }

    public function desc(): Query
    {
        return $this->setLastSortType(Sort::DESC);
    }

    public function natural(): Query
    {
        return $this->setLastSortType(Sort::NATSORT);
    }

    public function shuffle(): Query
    {
        return $this->setLastSortType(Sort::SHUFFLE);
    }

    public function clearOrderings(): Query
    {
        $this->orderings = [];
        return $this;
    }

    /**
     * @param \Generator<StreamProviderArrayIteratorValue> $iterator
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function applySorting(\Generator $iterator): \Generator
    {
        if ($this->orderings === []) {
            return $iterator;
        }

        $data = iterator_to_array($iterator);
        foreach ($this->orderings as $field => $type) {
            switch ($type) {
                case Sort::ASC:
                    usort($data, fn($a, $b) => ($a[$field] ?? null) <=> ($b[$field] ?? null));
                    break;

                case Sort::DESC:
                    usort($data, fn($a, $b) => ($b[$field] ?? null) <=> ($a[$field] ?? null));
                    break;

                case Sort::NATSORT:
                    usort($data, function ($a, $b) use ($field) {
                        $valA = $a[$field] ?? '';
                        $valB = $b[$field] ?? '';
                        return strnatcmp((string)$valA, (string)$valB);
                    });
                    break;

                case Sort::SHUFFLE:
                    shuffle($data);
                    break;

                default:
                    throw new InvalidArgumentException(sprintf('Unsupported sort type: %s', $type->value));
            }
        }

        $stream = new \ArrayIterator($data);
        foreach ($stream as $item) {
            yield $item;
        }
    }

    private function orderByToString(): string
    {
        if (empty($this->orderings)) {
            return '';
        }

        $orderings = array_map(
            fn($field, Sort $type) => sprintf('%s %s', $field, strtoupper($type->value)),
            array_keys($this->orderings),
            $this->orderings
        );

        return sprintf("\nORDER BY %s", implode(', ', $orderings));
    }

    private function setLastSortType(Sort $type): Query
    {
        $lastField = array_key_last($this->orderings);
        if ($lastField === null) {
            throw new InvalidArgumentException('No field available to set sorting type.');
        }

        $this->orderings[$lastField] = $type;
        return $this;
    }
}
