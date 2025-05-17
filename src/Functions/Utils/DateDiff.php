<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\MultipleFieldsFunction;

class DateDiff extends MultipleFieldsFunction
{
    /**
     * @param string $dateField
     * @param string $dateField2
     */
    public function __construct(private readonly string $dateField, private readonly string $dateField2)
    {
        parent::__construct($dateField, $dateField2);
    }

    public function __invoke(array $item, array $resultItem): ?int
    {
        $date = $this->getFieldValue($this->dateField, $item, $resultItem);
        $dateCompare = $this->getFieldValue($this->dateField2, $item, $resultItem);
        if (
            !is_string($date)
            || !is_string($dateCompare)
            || strtotime($date) === false
            || strtotime($dateCompare) === false
        ) {
            return null;
        }

        try {
            $date = new \DateTime($date);
            $dateCompare = new \DateTime($dateCompare);
            return (int) $date->diff($dateCompare)->format('%r%a');
        } catch (\Exception) {
            return null;
        }
    }
}
