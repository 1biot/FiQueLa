<?php

namespace FQL\Enum;

enum Sort: string
{
    case ASC = 'asc';
    case DESC = 'desc';
    case SHUFFLE = 'shuffle';
    case NATSORT = 'natsort';
}
