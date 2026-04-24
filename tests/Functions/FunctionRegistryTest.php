<?php

namespace Functions;

use FQL\Exception\FunctionRegistrationException;
use FQL\Functions\Core\AggregateFunction;
use FQL\Functions\Core\ScalarFunction;
use FQL\Functions\FunctionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Covers the global FunctionRegistry lifecycle — neon-driven bootstrap,
 * cache write/read, user register/override/unregister, and loadConfig merge.
 *
 * Every test resets the registry so assertions run against a clean slate.
 * `setCacheDir()` is pointed at a unique temp directory per test to keep
 * cached `.compiled.php` files from leaking into the shared library dir.
 */
final class FunctionRegistryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        FunctionRegistry::reset();
        $this->tmpDir = sys_get_temp_dir() . '/fiquela-registry-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        FunctionRegistry::setCacheDir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        FunctionRegistry::reset();
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        @rmdir($this->tmpDir);
    }

    public function testBuiltinNeonDoesNotBootstrapBeforeAccess(): void
    {
        // Nothing accessed yet; internal state still null.
        $reflection = new \ReflectionClass(FunctionRegistry::class);
        $scalar = $reflection->getProperty('scalar');
        $this->assertNull($scalar->getValue());
    }

    public function testBootstrapLoadsBuiltinsFromNeon(): void
    {
        // Built-in neon is shipped with the library and must register all 60+
        // scalar functions and all 6 aggregates.
        $all = FunctionRegistry::all();
        $this->assertArrayHasKey('scalar', $all);
        $this->assertArrayHasKey('aggregate', $all);
        $this->assertGreaterThan(50, count($all['scalar']));
        $this->assertSame(6, count($all['aggregate']));
        $this->assertArrayHasKey('LOWER', $all['scalar']);
        $this->assertArrayHasKey('SUM', $all['aggregate']);
    }

    public function testCaseInsensitiveLookup(): void
    {
        $this->assertTrue(FunctionRegistry::has('lower'));
        $this->assertTrue(FunctionRegistry::has('LOWER'));
        $this->assertTrue(FunctionRegistry::has('Lower'));
    }

    public function testIsAggregate(): void
    {
        $this->assertTrue(FunctionRegistry::isAggregate('SUM'));
        $this->assertFalse(FunctionRegistry::isAggregate('LOWER'));
        $this->assertFalse(FunctionRegistry::isAggregate('NONEXISTENT'));
    }

    public function testRegisterScalar(): void
    {
        FunctionRegistry::register(RegistryTestCustomScalar::class);
        $this->assertSame(
            RegistryTestCustomScalar::class,
            FunctionRegistry::getScalar('CUSTOM_SCALAR')
        );
    }

    public function testRegisterStrictOnDuplicate(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        $this->expectExceptionMessage('already registered');
        FunctionRegistry::register(RegistryTestDuplicateLower::class);
    }

    public function testOverrideReplacesExisting(): void
    {
        $before = FunctionRegistry::getScalar('LOWER');
        $this->assertNotSame(RegistryTestDuplicateLower::class, $before);

        FunctionRegistry::override(RegistryTestDuplicateLower::class);
        $this->assertSame(RegistryTestDuplicateLower::class, FunctionRegistry::getScalar('LOWER'));
    }

    public function testUnregisterBuiltin(): void
    {
        $this->assertTrue(FunctionRegistry::has('UUID'));
        FunctionRegistry::unregister('UUID');
        $this->assertFalse(FunctionRegistry::has('UUID'));
    }

    public function testUnregisterUnknownThrows(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        FunctionRegistry::unregister('NOPE');
    }

    public function testRegisterRejectsMissingClass(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        $this->expectExceptionMessage('does not exist');
        /** @phpstan-ignore-next-line */
        FunctionRegistry::register('Foo\\Bar\\Baz\\NotAClass');
    }

    public function testRegisterRejectsClassNotImplementingInterface(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        $this->expectExceptionMessage('must implement');
        FunctionRegistry::register(RegistryTestBadClass::class);
    }

    public function testLoadConfigMergesAdditionalScalar(): void
    {
        $neonPath = $this->tmpDir . '/custom.neon';
        file_put_contents(
            $neonPath,
            "scalar:\n    - " . RegistryTestCustomScalar::class . "\n"
        );
        FunctionRegistry::loadConfig($neonPath);
        $this->assertSame(
            RegistryTestCustomScalar::class,
            FunctionRegistry::getScalar('CUSTOM_SCALAR')
        );
    }

    public function testLoadConfigThrowsOnMissingFile(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        FunctionRegistry::loadConfig($this->tmpDir . '/missing.neon');
    }

    public function testCacheFileIsWrittenAndReused(): void
    {
        FunctionRegistry::getScalar('LOWER'); // force bootstrap
        $cacheFile = $this->tmpDir . '/fiquela-functions.compiled.php';
        $this->assertFileExists($cacheFile);

        // Reset in-memory, but leave the on-disk cache — next bootstrap must
        // re-populate from the cache file (no neon parsing needed).
        FunctionRegistry::reset();
        FunctionRegistry::setCacheDir($this->tmpDir);

        $this->assertSame('FQL\\Functions\\String\\Lower', FunctionRegistry::getScalar('LOWER'));
    }

    public function testAllReturnsSnapshot(): void
    {
        $snapshot = FunctionRegistry::all();
        $this->assertNotEmpty($snapshot['scalar']);
        $this->assertNotEmpty($snapshot['aggregate']);
    }
}

/** @internal */
final class RegistryTestCustomScalar implements ScalarFunction
{
    public static function name(): string
    {
        return 'CUSTOM_SCALAR';
    }

    public static function execute(mixed $v): string
    {
        return (string) $v;
    }
}

/** @internal */
final class RegistryTestDuplicateLower implements ScalarFunction
{
    public static function name(): string
    {
        return 'LOWER';
    }

    public static function execute(mixed $v): string
    {
        return strtolower((string) $v);
    }
}

/** @internal — intentionally does not implement any function contract */
final class RegistryTestBadClass
{
    public static function name(): string
    {
        return 'BAD';
    }
}
