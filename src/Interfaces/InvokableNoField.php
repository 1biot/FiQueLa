<?php

namespace FQL\Interfaces;

interface InvokableNoField
{
    public function getName(): string;

    public function __invoke(): mixed;
}
