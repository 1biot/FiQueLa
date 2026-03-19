<?php

namespace FQL\Conditions;

use FQL\Enum;

final class IfStatementConditionGroup extends BaseConditionGroup
{
    public function __construct()
    {
        parent::__construct(Enum\LogicalOperator::AND);
    }

    public function render(): string
    {
        return $this->renderConditions();
    }
}
