<?php

namespace FQL\Interface;

interface InvokableNoField
{
    public function getName(): string;

    public function __invoke(): mixed;
}
