<?php

namespace FQL\Functions\Core;

interface InvokableNoField
{
    public function getName(): string;

    public function __invoke(): mixed;
}
