<?php

namespace FQL\Conditions;

use FQL\Enum;

abstract class BaseConditionGroup extends GroupCondition
{
    protected function renderConditions(): string
    {
        $return = '';

        foreach ($this->getConditions() as $index => $entry) {
            $index > 0
                && $return .= $entry->logicalOperator->render(true);
            $return .= $entry->condition->render();
        }

        return $return;
    }
}
