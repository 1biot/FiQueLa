<?php

namespace FQL\Cli\Config;

use FQL\Client\Dto\AuthToken;
use RuntimeException;

class SessionManager
{
    private const REQUIRED_PERMISSIONS = 0600;

    private string $sessionsFile;

    public function __construct(string $configDir)
    {
        $this->sessionsFile = $configDir . '/sessions.json';
    }

    public function getSessionsFile(): string
    {
        return $this->sessionsFile;
    }

    /**
     * Check if sessions.json has correct file permissions (600).
     */
    public function validatePermissions(): bool
    {
        if (!file_exists($this->sessionsFile)) {
            return true; // File doesn't exist yet, will be created with correct perms
        }

        $perms = fileperms($this->sessionsFile);
        if ($perms === false) {
            return false;
        }

        return ($perms & 0777) === self::REQUIRED_PERMISSIONS;
    }

    /**
     * Get stored token for a server URL.
     */
    public function getToken(string $serverUrl): ?AuthToken
    {
        $sessions = $this->loadSessions();
        $serverUrl = rtrim($serverUrl, '/');

        if (!isset($sessions[$serverUrl])) {
            return null;
        }

        $data = $sessions[$serverUrl];
        $tokenString = $data['token'];

        if ($tokenString === '') {
            return null;
        }

        $token = new AuthToken($tokenString);

        // Check expiration
        if ($token->isExpired()) {
            $this->removeToken($serverUrl);
            return null;
        }

        return $token;
    }

    /**
     * Store a token for a server URL.
     */
    public function saveToken(string $serverUrl, AuthToken $token): void
    {
        $sessions = $this->loadSessions();
        $serverUrl = rtrim($serverUrl, '/');

        $sessions[$serverUrl] = [
            'token' => $token->token,
            'expires_at' => $token->getExpiresAt(),
        ];

        $this->saveSessions($sessions);
    }

    /**
     * Remove a stored token for a server URL.
     */
    public function removeToken(string $serverUrl): void
    {
        $sessions = $this->loadSessions();
        $serverUrl = rtrim($serverUrl, '/');

        unset($sessions[$serverUrl]);
        $this->saveSessions($sessions);
    }

    /**
     * @return array<string, array{token: string, expires_at: int|null}>
     */
    private function loadSessions(): array
    {
        if (!file_exists($this->sessionsFile)) {
            return [];
        }

        $content = file_get_contents($this->sessionsFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * @param array<string, array{token: string, expires_at: int|null}> $sessions
     */
    private function saveSessions(array $sessions): void
    {
        $dir = dirname($this->sessionsFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new RuntimeException(
                    sprintf('Failed to create directory: %s', $dir)
                );
            }
        }

        $json = json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode sessions to JSON.');
        }

        $result = file_put_contents($this->sessionsFile, $json . "\n");
        if ($result === false) {
            throw new RuntimeException(
                sprintf('Failed to write sessions file: %s', $this->sessionsFile)
            );
        }

        chmod($this->sessionsFile, self::REQUIRED_PERMISSIONS);
    }
}
