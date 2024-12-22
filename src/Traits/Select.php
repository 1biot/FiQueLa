<?php

namespace UQL\Traits;

use UQL\Exceptions\InvalidArgumentException;
use UQL\Helpers\ArrayHelper;
use UQL\Query\Query;

trait Select
{
    /** @var array<string, array{originField: string, alias: bool, function: ?array{name: string, parameters: mixed}}> $selectedFields */
    private array $selectedFields = [];

    public function selectAll(): Query
    {
        $this->select(Query::SELECT_ALL);
        return $this;
    }

    public function select(string $fields): Query
    {
        $fields = array_map('trim', explode(',', $fields));
        foreach ($fields as $field) {
            if ($field === Query::SELECT_ALL) {
                $this->selectedFields = [];
                continue;
            }

            if (isset($this->selectedFields[$field])) {
                throw new \InvalidArgumentException(sprintf('Field "%s" already defined', $field));
            }

            $this->selectedFields[$field] = [
                'originField' => $field,
                'alias' => false,
                'function' => null,
            ];
        }

        return $this;
    }

    public function as(string $alias): Query
    {
        if ($alias === '') {
            throw new InvalidArgumentException('Alias cannot be empty');
        }

        $select = array_key_last($this->selectedFields);
        if ($select === null) {
            throw new InvalidArgumentException('Cannot use alias without a field');
        } elseif ($this->selectedFields[$select]['alias']) {
            throw new InvalidArgumentException('Cannot use alias repeatedly');
        } elseif (isset($this->selectedFields[$alias])) {
            throw new InvalidArgumentException(sprintf('Alias "%s" already defined', $alias));
        }

        unset($this->selectedFields[$select]);
        $this->alias($select, $alias);
        return $this;
    }

    private function alias(string $field, string $alias): Query
    {
        $this->selectedFields[$alias] = [
            'originField' => $field,
            'alias' => true,
            'function' => null,
        ];

        return $this;
    }

    /**
     * @param array<string|int, mixed> $item
     * @return array<string|int, mixed>
     */
    private function applySelect(array $item): array
    {
        if ($this->selectedFields === []) {
            return $item;
        }

        $result = [];
        foreach ($this->selectedFields as $finalField => $fieldData) {
            $fieldName = $finalField;
            $value = $fieldData['alias']
                ? ArrayHelper::getNestedValue($item, $fieldData['originField'], false)
                : ArrayHelper::getNestedValue($item, $finalField, false);

            $result[$fieldName] = $value;
        }

        return $result;
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
            $return .= "\n\t" . $fieldData['originField'];
            if ($fieldData['alias']) {
                $return .= ' ' . Query::AS . ' ' . $finalField;
            }

            if ($counter++ < $count) {
                $return .= ',';
            }
        }

        return $return;
    }
}
