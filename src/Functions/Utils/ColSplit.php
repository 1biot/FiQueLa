<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\SingleFieldFunctionByReference;
use FQL\Stream\ArrayStreamProvider;
use FQL\Exception\InvalidArgumentException;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
class ColSplit extends SingleFieldFunctionByReference
{
    /**
     * @param string $field
     * @param string|null $format
     * @param string|null $keyField
     */
    public function __construct(
        string $field,
        protected readonly ?string $format = null,
        protected readonly ?string $keyField = null
    ) {
        parent::__construct($field);
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     * @param StreamProviderArrayIteratorValue $resultItem
     * @throws InvalidArgumentException
     */
    public function __invoke(array $item, array &$resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem);

        if (!is_array($value)) {
            return null; // Nic nerozdělujeme, hodnota není list
        }

        $baseName = $this->sanitizeFieldName($this->field);
        $format = $this->format ?? "{$baseName}_%index";

        foreach (array_values($value) as $i => $entry) {
            $suffix = $this->getSuffixFromEntry($entry, $i);
            $colName = str_replace('%index', $suffix, $format);
            $resultItem[$colName] = $entry;
        }

        return null; // Výsledky jsou přidány přes referenci
    }

    protected function getSuffixFromEntry(mixed $entry, int $index): string|int
    {
        if ($this->keyField && is_array($entry) && array_key_exists($this->keyField, $entry)) {
            return $entry[$this->keyField];
        }

        return $index + 1;
    }

    protected function sanitizeFieldName(string $field): string
    {
        return str_replace(['.', '[', ']'], '_', $field);
    }

    public function __toString(): string
    {
        $params = [$this->field];

        if ($this->format !== null || $this->keyField !== null) {
            $params[] = $this->format !== null ? '"' . $this->format . '"' : 'null';
        }

        if ($this->keyField !== null) {
            if (!isset($params[1])) {
                $params[] = 'null';
            }
            $params[] = '"' . $this->keyField . '"';
        }

        return sprintf(
            '%s(%s)',
            $this->getName(),
            implode(', ', $params)
        );
    }
}
