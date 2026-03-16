<?php

namespace FQL\Client\Dto;

class AuthToken
{
    public function __construct(
        public readonly string $token
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: (string) ($data['token'] ?? $data['revoked'] ?? '')
        );
    }

    /**
     * Decode JWT payload without verification (for reading expiration, etc.)
     * @return array<string, mixed>
     */
    public function decodePayload(): array
    {
        $parts = explode('.', $this->token);
        if (count($parts) !== 3) {
            return [];
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check if the token is expired based on the 'exp' claim
     */
    public function isExpired(): bool
    {
        $payload = $this->decodePayload();
        if (!isset($payload['exp'])) {
            return true;
        }

        return (int) $payload['exp'] <= time();
    }

    /**
     * Get the expiration timestamp
     */
    public function getExpiresAt(): ?int
    {
        $payload = $this->decodePayload();
        return isset($payload['exp']) ? (int) $payload['exp'] : null;
    }
}
