<?php

namespace FQL\Conditions;

use FQL\Enum;

final class WhereConditionGroup extends BaseConditionGroup
{
    public function __construct()
    {
        parent::__construct(Enum\LogicalOperator::AND);
    }

    public function render(): string
    {
        return 'WHERE' . PHP_EOL . "\t" . $this->renderConditions();
    }
}
