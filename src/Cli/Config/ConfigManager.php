<?php

namespace FQL\Cli\Config;

use RuntimeException;

class ConfigManager
{
    private const REQUIRED_PERMISSIONS = 0600;
    private const REQUIRED_PERMISSIONS_STRING = '0600';

    private string $configDir;
    private string $authFile;

    public function __construct(?string $configDir = null)
    {
        $this->configDir = $configDir ?? $this->getDefaultConfigDir();
        $this->authFile = $this->configDir . '/auth.json';
    }

    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    public function getAuthFile(): string
    {
        return $this->authFile;
    }

    /**
     * Ensure the ~/.fql directory exists with proper permissions.
     */
    public function ensureConfigDir(): void
    {
        if (!is_dir($this->configDir)) {
            if (!mkdir($this->configDir, 0700, true)) {
                throw new RuntimeException(
                    sprintf('Failed to create config directory: %s', $this->configDir)
                );
            }
        }
    }

    /**
     * Check if auth.json exists.
     */
    public function hasAuthFile(): bool
    {
        return file_exists($this->authFile);
    }

    /**
     * Validate that auth.json has correct file permissions (600).
     */
    public function validateAuthFilePermissions(): bool
    {
        if (!$this->hasAuthFile()) {
            return false;
        }

        clearstatcache(true, $this->authFile);

        $perms = fileperms($this->authFile);
        if ($perms === false) {
            return false;
        }

        // Check only the last 9 bits (owner/group/other rwx)
        return ($perms & 0777) === self::REQUIRED_PERMISSIONS;
    }

    /**
     * Load all servers from auth.json.
     * @return ServerConfig[]
     */
    public function loadServers(): array
    {
        if (!$this->hasAuthFile()) {
            return [];
        }

        $content = file_get_contents($this->authFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        $servers = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $server = ServerConfig::fromArray($item);
                if ($server->isValid()) {
                    $servers[] = $server;
                }
            }
        }

        return $servers;
    }

    /**
     * Find a server by name.
     */
    public function findServer(string $name): ?ServerConfig
    {
        foreach ($this->loadServers() as $server) {
            if ($server->name === $name) {
                return $server;
            }
        }

        return null;
    }

    /**
     * Add a server to auth.json.
     */
    public function addServer(ServerConfig $server): void
    {
        $this->ensureConfigDir();

        $servers = $this->loadServers();

        // Replace existing server with same name
        $found = false;
        foreach ($servers as $key => $existing) {
            if ($existing->name === $server->name) {
                $servers[$key] = $server;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $servers[] = $server;
        }

        $this->saveServers($servers);
    }

    /**
     * Remove a server from auth.json.
     */
    public function removeServer(string $name): bool
    {
        $servers = $this->loadServers();
        $filtered = array_filter($servers, fn(ServerConfig $s) => $s->name !== $name);

        if (count($filtered) === count($servers)) {
            return false;
        }

        $this->saveServers(array_values($filtered));
        return true;
    }

    /**
     * Save servers to auth.json with correct permissions.
     * @param ServerConfig[] $servers
     */
    private function saveServers(array $servers): void
    {
        $this->ensureConfigDir();

        $data = array_map(fn(ServerConfig $s) => $s->toArray(), $servers);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode server configurations to JSON.');
        }

        $result = file_put_contents($this->authFile, $json . "\n");
        if ($result === false) {
            throw new RuntimeException(
                sprintf('Failed to write auth file: %s', $this->authFile)
            );
        }

        chmod($this->authFile, self::REQUIRED_PERMISSIONS);
    }

    /**
     * Get required permissions as a human-readable string.
     */
    public static function getRequiredPermissionsString(): string
    {
        return self::REQUIRED_PERMISSIONS_STRING;
    }

    private function getDefaultConfigDir(): string
    {
        $home = getenv('HOME');
        if ($home === false || $home === '') {
            $home = getenv('USERPROFILE');
        }

        if ($home === false || $home === '') {
            throw new RuntimeException('Unable to determine home directory.');
        }

        return $home . '/.fql';
    }
}
