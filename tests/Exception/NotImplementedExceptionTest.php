<?php

namespace Exception;

use FQL\Exception\NotImplementedException;
use PHPUnit\Framework\TestCase;

class NotImplementedExceptionTest extends TestCase
{
    public function testMessageForFunctionString(): void
    {
        $exception = new NotImplementedException('strlen');

        $this->assertSame('Function strlen not implemented yet.', $exception->getMessage());
    }

    public function testMessageForStaticMethodString(): void
    {
        $exception = new NotImplementedException(StaticCallable::class . '::run');

        $this->assertSame(
            'Static method ' . StaticCallable::class . '::run not implemented yet.',
            $exception->getMessage()
        );
    }

    public function testMessageForObjectMethodArray(): void
    {
        $object = new class {
            public function run(): void
            {
            }
        };

        $exception = new NotImplementedException([$object, 'run']);

        $this->assertStringContainsString('Object method', $exception->getMessage());
        $this->assertStringContainsString('->run not implemented yet.', $exception->getMessage());
    }

    public function testMessageForStaticMethodArray(): void
    {
        $exception = new NotImplementedException([StaticCallable::class, 'run']);

        $this->assertSame('Static method ' . StaticCallable::class . '::run not implemented yet.', $exception->getMessage());
    }

    public function testMessageForClosure(): void
    {
        $exception = new NotImplementedException(function (): void {
        });

        $this->assertSame('Anonymous function (closure) not implemented yet.', $exception->getMessage());
    }

    public function testMessageForInvokableObject(): void
    {
        $object = new InvokableCallable();

        $exception = new NotImplementedException($object);

        $this->assertSame('Invokable object ' . $object::class . ' not implemented yet.', $exception->getMessage());
    }
}

final class StaticCallable
{
    public static function run(): void
    {
    }
}

final class InvokableCallable
{
    public function __invoke(): void
    {
    }
}
