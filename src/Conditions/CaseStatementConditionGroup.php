<?php

namespace FQL\Conditions;

use FQL\Enum;

final class CaseStatementConditionGroup extends BaseConditionGroup
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
