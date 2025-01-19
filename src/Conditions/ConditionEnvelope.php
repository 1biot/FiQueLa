<?php

namespace FQL\Conditions;

use FQL\Enum;

class ConditionEnvelope
{
    public function __construct(
        public readonly Enum\LogicalOperator $logicalOperator,
        public readonly Condition $condition
    ) {
    }
}
