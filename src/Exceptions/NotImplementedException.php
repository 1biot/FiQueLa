<?php

namespace FQL\Exceptions;

class NotImplementedException extends Exception
{
    public function __construct(callable $notImplemented, int $code = 0, ?\Throwable $previous = null)
    {
        $message = $this->analyzeCallable($notImplemented);
        parent::__construct(sprintf('%s not implemented yet.', $message), $code, $previous);
    }

    private function analyzeCallable(callable $callable): string
    {
        if (is_string($callable)) {
            return str_contains($callable, '::')
                ? "Static method {$callable}"
                : "Function {$callable}";
        }

        if (is_array($callable)) {
            [$classOrObject, $method] = $callable;

            if (is_object($classOrObject)) {
                return "Object method " . get_class($classOrObject) . "->{$method}";
            }

            return "Static method {$classOrObject}::{$method}";
        }

        if ($callable instanceof \Closure) {
            return "Anonymous function (closure)";
        }

        if (is_object($callable)) {
            return "Invokable object " . $callable::class;
        }

        return "Unknown callable type";
    }
}
