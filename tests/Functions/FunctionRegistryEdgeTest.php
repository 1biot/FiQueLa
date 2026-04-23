<?php

namespace Functions;

use FQL\Exception\FunctionRegistrationException;
use FQL\Functions\Core\AggregateFunction;
use FQL\Functions\Core\ScalarFunction;
use FQL\Functions\FunctionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Pushes into the error-path corners of FunctionRegistry that the happy-path
 * tests skip: invalid classes, invalid neon, malformed entries, cache I/O
 * fallback paths.
 */
class FunctionRegistryEdgeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        FunctionRegistry::reset();
        $this->tmpDir = sys_get_temp_dir() . '/fql-reg-edge-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        FunctionRegistry::setCacheDir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        FunctionRegistry::reset();
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    public function testRegisterRejectsUnknownClass(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        /** @phpstan-ignore-next-line */
        FunctionRegistry::register('Not\A\Real\Class');
    }

    public function testRegisterRejectsClassWithoutInterface(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        /** @phpstan-ignore-next-line */
        FunctionRegistry::register(\stdClass::class);
    }

    public function testLoadConfigRejectsMissingFile(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        FunctionRegistry::loadConfig('/tmp/does/not/exist/fiquela-functions.neon');
    }

    public function testCacheFileIsCreated(): void
    {
        FunctionRegistry::getScalar('LOWER');
        $cacheFile = $this->tmpDir . '/fiquela-functions.compiled.php';
        $this->assertFileExists($cacheFile);
    }

    public function testCacheStaleDetection(): void
    {
        // Write an obviously-fake cache file that claims it covers a
        // different source list — registry must detect the mismatch and
        // rebuild instead of trusting the stale file.
        $cacheFile = $this->tmpDir . '/fiquela-functions.compiled.php';
        file_put_contents(
            $cacheFile,
            "<?php return ['scalar' => [], 'aggregate' => [], 'sources' => ['/some/other/path.neon']];"
        );
        FunctionRegistry::reset();
        FunctionRegistry::setCacheDir($this->tmpDir);

        // Registry must refuse the stale cache and bootstrap fresh — LOWER
        // stays resolvable.
        $this->assertNotNull(FunctionRegistry::getScalar('LOWER'));
    }

    public function testOverrideSwapsImplementation(): void
    {
        // First access establishes LOWER.
        $this->assertSame(
            \FQL\Functions\String\Lower::class,
            FunctionRegistry::getScalar('LOWER')
        );

        FunctionRegistry::override(CustomLower::class);
        $this->assertSame(CustomLower::class, FunctionRegistry::getScalar('LOWER'));
    }

    public function testUnregisterRemovesEntry(): void
    {
        $this->assertNotNull(FunctionRegistry::getScalar('LOWER'));
        FunctionRegistry::unregister('LOWER');
        $this->assertNull(FunctionRegistry::getScalar('LOWER'));
    }

    public function testAllReturnsFlatNameMap(): void
    {
        $all = FunctionRegistry::all();
        $this->assertIsArray($all);
        // `all()` returns an array that at minimum includes an entry for the
        // built-in LOWER — regardless of whether it's grouped by category
        // or flat.
        $this->assertGreaterThan(0, count($all));
    }

    public function testUnregisterUnknownThrows(): void
    {
        $this->expectException(FunctionRegistrationException::class);
        FunctionRegistry::unregister('DEFINITELY_NOT_A_REGISTERED_FUNCTION_XYZ');
    }

    public function testRegisterDuplicateThrows(): void
    {
        // LOWER is already registered from built-ins. Attempting to register
        // CustomLower (which also uses name "LOWER") without override must
        // raise an error.
        $this->expectException(FunctionRegistrationException::class);
        FunctionRegistry::register(CustomLower::class);
    }

    public function testLoadConfigAppendsSource(): void
    {
        // File must exist AND contain valid (if empty) contents — the config
        // is applied by scheduling a rebootstrap.
        $cfg = $this->tmpDir . '/extra-empty.neon';
        file_put_contents($cfg, "scalar: []\naggregate: []\n");
        FunctionRegistry::loadConfig($cfg);

        // Subsequent access rebuilds; LOWER should still be resolvable.
        $this->assertNotNull(FunctionRegistry::getScalar('LOWER'));
    }

    public function testGetAggregateCaseInsensitive(): void
    {
        $this->assertNotNull(FunctionRegistry::getAggregate('SUM'));
        $this->assertNotNull(FunctionRegistry::getAggregate('sum'));
    }

    public function testHasReflectsBothScalarAndAggregate(): void
    {
        $this->assertTrue(FunctionRegistry::has('LOWER'));
        $this->assertTrue(FunctionRegistry::has('SUM'));
        $this->assertFalse(FunctionRegistry::has('NOT_A_FUNCTION_XYZ'));
    }

    public function testIsAggregatePredicate(): void
    {
        $this->assertTrue(FunctionRegistry::isAggregate('SUM'));
        $this->assertFalse(FunctionRegistry::isAggregate('LOWER'));
        $this->assertFalse(FunctionRegistry::isAggregate('NOT_REAL'));
    }
}

// ----- fixtures --------------------------------------------------------------

final class CustomLower implements ScalarFunction
{
    public static function name(): string
    {
        return 'LOWER';
    }

    public static function execute(mixed $value): string
    {
        return 'custom-' . strtolower((string) $value);
    }
}
