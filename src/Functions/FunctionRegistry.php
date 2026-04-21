<?php

namespace FQL\Functions;

use FQL\Exception\FunctionRegistrationException;
use FQL\Functions\Core\AggregateFunction;
use FQL\Functions\Core\ScalarFunction;
use Nette\Neon\Neon;

/**
 * Global static registry of FQL functions (scalar + aggregate).
 *
 * Loads the built-in list from `src/Functions/functions.neon` on first use,
 * optionally caches the resolved `name → class-string` map into a PHP file
 * for fast subsequent bootstraps. Users may register/unregister/override
 * functions at runtime and load additional neon configs.
 *
 * Case-insensitive: all names are stored upper-cased; `getScalar('lower')`
 * and `getScalar('LOWER')` are equivalent.
 *
 * Duplicate registration is strict — registering a name already present throws
 * {@see FunctionRegistrationException}. Use {@see override()} to explicitly
 * replace an existing entry.
 *
 * Cache resolution (by priority):
 *  1. `setCacheDir($dir)` — explicit user location
 *  2. library root (`__DIR__`) if writable
 *  3. `sys_get_temp_dir()`
 *  4. no cache — parse neon every bootstrap
 *
 * `loadConfig($path)` appends the given neon file as an additional source;
 * the cache key incorporates the source list + mtimes for invalidation.
 */
final class FunctionRegistry
{
    private const CACHE_FILENAME = 'fiquela-functions.compiled.php';

    /** @var array<string, class-string<ScalarFunction>>|null */
    private static ?array $scalar = null;

    /** @var array<string, class-string<AggregateFunction>>|null */
    private static ?array $aggregate = null;

    private static ?string $cacheDir = null;

    /** @var string[] */
    private static array $sources = [];

    /**
     * Configure where the compiled cache file lives. Pass `null` to fall back
     * to the default resolution (library root → system temp → none).
     */
    public static function setCacheDir(?string $dir): void
    {
        self::$cacheDir = $dir;
        // Invalidate in-memory state so the next access re-bootstraps with the
        // new cache location.
        self::$scalar = null;
        self::$aggregate = null;
    }

    /**
     * Appends a user neon config to the registry sources and re-bootstraps.
     * The file follows the same format as `functions.neon`:
     *
     * ```neon
     * scalar:
     *     - My\App\Functions\Slugify
     * aggregate:
     *     - My\App\Functions\Median
     * ```
     *
     * Entries merge with (or override) the built-in list depending on
     * name collisions — colliding names follow `register()`'s strict policy
     * and throw; explicit override requires {@see override()}.
     */
    public static function loadConfig(string $neonPath): void
    {
        if (!is_file($neonPath) || !is_readable($neonPath)) {
            throw new FunctionRegistrationException(
                sprintf('Function config file not found or unreadable: %s', $neonPath)
            );
        }
        if (!in_array($neonPath, self::$sources, true)) {
            self::$sources[] = $neonPath;
        }
        self::$scalar = null;
        self::$aggregate = null;
    }

    /**
     * Registers a new function class. Throws on duplicate name — use
     * {@see override()} to replace an existing entry.
     *
     * @param class-string $class
     */
    public static function register(string $class): void
    {
        self::ensureBootstrapped();
        self::registerInternal($class, override: false);
        self::writeCache();
    }

    /**
     * Registers a function class, replacing any existing entry with the same
     * name. Useful for swapping built-ins (e.g. a stricter `Lower`).
     *
     * @param class-string $class
     */
    public static function override(string $class): void
    {
        self::ensureBootstrapped();
        self::registerInternal($class, override: true);
        self::writeCache();
    }

    /**
     * Removes a function by name (built-in or user-registered). Throws when
     * the name is unknown.
     */
    public static function unregister(string $name): void
    {
        self::ensureBootstrapped();
        $upper = strtoupper($name);
        if (isset(self::$scalar[$upper])) {
            unset(self::$scalar[$upper]);
        } elseif (isset(self::$aggregate[$upper])) {
            unset(self::$aggregate[$upper]);
        } else {
            throw new FunctionRegistrationException(
                sprintf('Cannot unregister unknown function "%s"', $name)
            );
        }
        self::writeCache();
    }

    /**
     * Returns the scalar implementation class for a name, or `null` when the
     * name is unknown or refers to an aggregate.
     *
     * @return class-string<ScalarFunction>|null
     */
    public static function getScalar(string $name): ?string
    {
        self::ensureBootstrapped();
        return self::$scalar[strtoupper($name)] ?? null;
    }

    /**
     * Returns the aggregate implementation class for a name, or `null` when
     * the name is unknown or refers to a scalar function.
     *
     * @return class-string<AggregateFunction>|null
     */
    public static function getAggregate(string $name): ?string
    {
        self::ensureBootstrapped();
        return self::$aggregate[strtoupper($name)] ?? null;
    }

    public static function has(string $name): bool
    {
        self::ensureBootstrapped();
        $upper = strtoupper($name);
        return isset(self::$scalar[$upper]) || isset(self::$aggregate[$upper]);
    }

    public static function isAggregate(string $name): bool
    {
        self::ensureBootstrapped();
        return isset(self::$aggregate[strtoupper($name)]);
    }

    /**
     * Full snapshot of the registry keyed by upper-cased name.
     *
     * @return array{scalar: array<string, class-string>, aggregate: array<string, class-string>}
     */
    public static function all(): array
    {
        self::ensureBootstrapped();
        return [
            'scalar' => self::$scalar ?? [],
            'aggregate' => self::$aggregate ?? [],
        ];
    }

    /**
     * Clears all in-memory state. Next access re-bootstraps from the source
     * neon files (and the existing cache, if valid). Intended for tests;
     * production code should rarely need this.
     */
    public static function reset(): void
    {
        self::$scalar = null;
        self::$aggregate = null;
        self::$sources = [];
        self::$cacheDir = null;
    }

    /* ----------------------------------------------------------------- */
    /*  Internals                                                        */
    /* ----------------------------------------------------------------- */

    private static function ensureBootstrapped(): void
    {
        if (self::$scalar !== null && self::$aggregate !== null) {
            return;
        }

        $sources = self::allSources();
        $cacheFile = self::cacheFilePath();
        if ($cacheFile !== null && self::isCacheFresh($cacheFile, $sources)) {
            $cached = self::loadCache($cacheFile);
            if ($cached !== null) {
                self::$scalar = $cached['scalar'];
                self::$aggregate = $cached['aggregate'];
                return;
            }
        }

        self::$scalar = [];
        self::$aggregate = [];
        foreach ($sources as $path) {
            self::loadSource($path);
        }
        self::writeCache();
    }

    /**
     * @param class-string $class
     */
    private static function registerInternal(string $class, bool $override): void
    {
        if (!class_exists($class)) {
            throw new FunctionRegistrationException(
                sprintf('Function class "%s" does not exist', $class)
            );
        }

        $isScalar = is_subclass_of($class, ScalarFunction::class);
        $isAggregate = is_subclass_of($class, AggregateFunction::class);

        if (!$isScalar && !$isAggregate) {
            throw new FunctionRegistrationException(sprintf(
                'Class "%s" must implement %s or %s',
                $class,
                ScalarFunction::class,
                AggregateFunction::class
            ));
        }
        if ($isScalar && $isAggregate) {
            throw new FunctionRegistrationException(sprintf(
                'Class "%s" implements both Scalar and Aggregate contracts — pick one',
                $class
            ));
        }

        /** @var class-string<ScalarFunction|AggregateFunction> $class */
        $name = strtoupper($class::name());
        if ($name === '') {
            throw new FunctionRegistrationException(sprintf(
                'Class "%s" returned an empty name()',
                $class
            ));
        }

        if (!$override) {
            if (isset(self::$scalar[$name]) || isset(self::$aggregate[$name])) {
                throw new FunctionRegistrationException(sprintf(
                    'Function "%s" is already registered. Use override() to replace it',
                    $name
                ));
            }
        } else {
            unset(self::$scalar[$name], self::$aggregate[$name]);
        }

        if ($isScalar) {
            /** @var class-string<ScalarFunction> $class */
            self::$scalar[$name] = $class;
        } else {
            /** @var class-string<AggregateFunction> $class */
            self::$aggregate[$name] = $class;
        }
    }

    /**
     * @return string[]
     */
    private static function allSources(): array
    {
        return array_merge([self::builtinNeonPath()], self::$sources);
    }

    private static function builtinNeonPath(): string
    {
        return __DIR__ . '/functions.neon';
    }

    private static function loadSource(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new FunctionRegistrationException(
                sprintf('Function source "%s" not found or unreadable', $path)
            );
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new FunctionRegistrationException(
                sprintf('Failed to read function source "%s"', $path)
            );
        }
        try {
            /** @var array{scalar?: string[], aggregate?: string[]}|mixed $decoded */
            $decoded = Neon::decode($raw);
        } catch (\Throwable $e) {
            throw new FunctionRegistrationException(
                sprintf('Malformed neon in "%s": %s', $path, $e->getMessage()),
                0,
                $e
            );
        }

        if (!is_array($decoded)) {
            throw new FunctionRegistrationException(
                sprintf('Function config "%s" must be a mapping with "scalar" / "aggregate" keys', $path)
            );
        }

        foreach ($decoded['scalar'] ?? [] as $class) {
            if (!is_string($class)) {
                throw new FunctionRegistrationException(
                    sprintf('Non-string scalar entry in "%s"', $path)
                );
            }
            /** @var class-string $class */
            self::registerInternal($class, override: false);
        }
        foreach ($decoded['aggregate'] ?? [] as $class) {
            if (!is_string($class)) {
                throw new FunctionRegistrationException(
                    sprintf('Non-string aggregate entry in "%s"', $path)
                );
            }
            /** @var class-string $class */
            self::registerInternal($class, override: false);
        }
    }

    private static function cacheFilePath(): ?string
    {
        $candidates = [];
        if (self::$cacheDir !== null) {
            $candidates[] = rtrim(self::$cacheDir, '/\\');
        }
        $candidates[] = __DIR__;
        $candidates[] = rtrim(sys_get_temp_dir(), '/\\');

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            if (is_writable($dir) || is_writable($dir . DIRECTORY_SEPARATOR . self::CACHE_FILENAME)) {
                return $dir . DIRECTORY_SEPARATOR . self::CACHE_FILENAME;
            }
        }
        return null;
    }

    /**
     * @param string[] $sources
     */
    private static function isCacheFresh(string $cacheFile, array $sources): bool
    {
        if (!is_file($cacheFile)) {
            return false;
        }
        $cacheMtime = filemtime($cacheFile);
        if ($cacheMtime === false) {
            return false;
        }
        foreach ($sources as $src) {
            if (!is_file($src)) {
                continue;
            }
            $srcMtime = filemtime($src);
            if ($srcMtime === false || $srcMtime > $cacheMtime) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array{scalar: array<string, class-string<ScalarFunction>>,
     *               aggregate: array<string, class-string<AggregateFunction>>,
     *               sources: string[]}|null
     */
    private static function loadCache(string $cacheFile): ?array
    {
        /** @var mixed $payload */
        $payload = @include $cacheFile;
        if (!is_array($payload) || !isset($payload['scalar'], $payload['aggregate'])) {
            return null;
        }
        // Verify cached source list still matches what we expect; if user
        // loaded additional configs since the cache was written, fall back
        // to full rebuild.
        $cachedSources = $payload['sources'] ?? [];
        if (!is_array($cachedSources) || $cachedSources !== self::allSources()) {
            return null;
        }
        /** @var array{scalar: array<string, class-string<ScalarFunction>>, aggregate: array<string, class-string<AggregateFunction>>, sources: string[]} $payload */
        return $payload;
    }

    private static function writeCache(): void
    {
        $path = self::cacheFilePath();
        if ($path === null) {
            return;
        }
        if (self::$scalar === null || self::$aggregate === null) {
            return;
        }

        $payload = [
            'scalar' => self::$scalar,
            'aggregate' => self::$aggregate,
            'sources' => self::allSources(),
        ];
        $content = "<?php\n\nreturn " . var_export($payload, true) . ";\n";
        @file_put_contents($path, $content, LOCK_EX);
    }
}
