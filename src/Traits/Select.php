<?php

namespace JQL\Traits;

use JQL\Helpers\ArrayHelper;
use JQL\Query;

trait Select
{
    /** @var string[] $selectedFields */
    private array $selectedFields = [];

    public function select(string $fields): Query
    {
        $this->selectedFields = array_merge($this->selectedFields, array_map('trim', explode(',', $fields)));
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
        foreach ($this->selectedFields as $field) {
            $value = ArrayHelper::getNestedValue($item, $field);
            if ($value !== null) {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    private function selectToString(): string
    {
        return 'SELECT ' . ($this->selectedFields === [] ? '*' : implode(', ', $this->selectedFields));
    }
}
