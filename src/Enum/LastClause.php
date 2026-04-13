<?php

namespace FQL\Enum;

enum LastClause: string
{
    case SELECT = 'select';
    case FROM = 'from';
    case JOIN = 'join';
}
