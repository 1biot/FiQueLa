<?php

namespace Functions;

use FQL\Exception\FunctionRegistrationException;
use FQL\Functions\Core\ScalarFunction;
use FQL\Functions\FunctionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Registering custom functions in the application bootstrap must work in
 * every process: the compiled cache holds only the built-in and
 * loadConfig() sources, never runtime register() / override() / unregister().
 *
 * A "process" is simulated with reset() + setCacheDir() (state reloaded
 * from the cache file); one test runs real PHP subprocesses.
 */
final class FunctionRegistryBootstrapTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/fql-reg-bootstrap-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->newProcess();
    }

    protected function tearDown(): void
    {
        FunctionRegistry::reset();
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    private function newProcess(): void
    {
        FunctionRegistry::reset();
        FunctionRegistry::setCacheDir($this->tmpDir);
    }

    private function cacheFile(): string
    {
        return $this->tmpDir . '/fiquela-functions.compiled.php';
    }

    public function testRegisterInBootstrapWorksInEveryProcess(): void
    {
        for ($process = 1; $process <= 3; $process++) {
            $this->newProcess();
            FunctionRegistry::register(BootstrapTestSlug::class);
            $this->assertSame(BootstrapTestSlug::class, FunctionRegistry::getScalar('BOOTSTRAP_SLUG'));
        }

        $this->assertFileExists($this->cacheFile());
        $this->assertStringNotContainsString('BootstrapTestSlug', (string) file_get_contents($this->cacheFile()));
    }

    public function testDuplicateRegisterInOneProcessStillThrows(): void
    {
        FunctionRegistry::register(BootstrapTestSlug::class);
        $this->expectException(FunctionRegistrationException::class);
        $this->expectExceptionMessage('already registered');
        FunctionRegistry::register(BootstrapTestSlug::class);
    }

    public function testOverrideDoesNotLeakIntoNextProcess(): void
    {
        FunctionRegistry::override(BootstrapTestLower::class);
        $this->assertSame(BootstrapTestLower::class, FunctionRegistry::getScalar('LOWER'));

        $this->newProcess();
        $this->assertSame('FQL\\Functions\\String\\Lower', FunctionRegistry::getScalar('LOWER'));
    }

    public function testUnregisterDoesNotLeakIntoNextProcess(): void
    {
        FunctionRegistry::unregister('UUID');
        $this->assertFalse(FunctionRegistry::has('UUID'));

        $this->newProcess();
        $this->assertTrue(FunctionRegistry::has('UUID'));
    }

    public function testLoadConfigFunctionsAreCached(): void
    {
        $neon = $this->tmpDir . '/custom.neon';
        file_put_contents($neon, "scalar:\n    - " . BootstrapTestSlug::class . "\n");
        FunctionRegistry::loadConfig($neon);
        $this->assertTrue(FunctionRegistry::has('BOOTSTRAP_SLUG'));
        $this->assertStringContainsString('BootstrapTestSlug', (string) file_get_contents($this->cacheFile()));

        $this->newProcess();
        FunctionRegistry::loadConfig($neon);
        $this->assertTrue(FunctionRegistry::has('BOOTSTRAP_SLUG'));
    }

    public function testCacheWithRuntimeRegistrationFromOlderVersionIsRebuilt(): void
    {
        // A 3.4.0 cache: no version key and a user function in the map.
        FunctionRegistry::getScalar('LOWER');
        $payload = include $this->cacheFile();
        $this->assertIsArray($payload);
        unset($payload['version']);
        $payload['scalar']['BOOTSTRAP_SLUG'] = BootstrapTestSlug::class;
        file_put_contents($this->cacheFile(), "<?php\n\nreturn " . var_export($payload, true) . ";\n");

        $this->newProcess();
        FunctionRegistry::register(BootstrapTestSlug::class);
        $this->assertSame(BootstrapTestSlug::class, FunctionRegistry::getScalar('BOOTSTRAP_SLUG'));
    }

    public function testRegisterInBootstrapWorksInSeparatePhpProcesses(): void
    {
        $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
        $script = $this->tmpDir . '/bootstrap.php';
        file_put_contents($script, <<<PHP
<?php
require {$this->export($autoload)};

final class SubprocessSlug implements \\FQL\\Functions\\Core\\ScalarFunction
{
    public static function name(): string
    {
        return 'SUBPROCESS_SLUG';
    }

    public static function execute(mixed \$value): string
    {
        return strtolower((string) \$value);
    }
}

\\FQL\\Functions\\FunctionRegistry::setCacheDir({$this->export($this->tmpDir)});
\\FQL\\Functions\\FunctionRegistry::register(SubprocessSlug::class);
echo \\FQL\\Functions\\FunctionRegistry::getScalar('subprocess_slug');
PHP);

        for ($run = 1; $run <= 2; $run++) {
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, sprintf('Run %d: %s', $run, implode("\n", $output)));
            $this->assertSame('SubprocessSlug', implode("\n", $output), sprintf('Run %d', $run));
        }
    }

    private function export(string|false $value): string
    {
        $this->assertIsString($value);
        return var_export($value, true);
    }
}

/** @internal */
final class BootstrapTestSlug implements ScalarFunction
{
    public static function name(): string
    {
        return 'BOOTSTRAP_SLUG';
    }

    public static function execute(mixed $value): string
    {
        return strtolower((string) $value);
    }
}

/** @internal */
final class BootstrapTestLower implements ScalarFunction
{
    public static function name(): string
    {
        return 'LOWER';
    }

    public static function execute(mixed $value): string
    {
        return strtolower((string) $value);
    }
}
