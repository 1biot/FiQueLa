<?php

namespace FQL\Conditions;

use FQL\Enum;

final class HavingConditionGroup extends BaseConditionGroup
{
    public function __construct()
    {
        parent::__construct(Enum\LogicalOperator::AND);
    }

    public function render(): string
    {
        return 'HAVING' . PHP_EOL . "\t" . $this->renderConditions();
    }
}
