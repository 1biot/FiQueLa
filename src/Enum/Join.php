<?php

namespace FQL\Enum;

enum Join: string
{
    case INNER = 'INNER JOIN';
    case LEFT = 'LEFT JOIN';
    case RIGHT = 'RIGHT JOIN';
    case FULL = 'FULL JOIN';
}
