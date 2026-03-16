<?php

namespace FQL\Cli\Config;

class ServerConfig
{
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly string $user,
        #[\SensitiveParameter]
        public readonly string $secret
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            url: rtrim((string) ($data['url'] ?? ''), '/'),
            user: (string) ($data['user'] ?? ''),
            secret: (string) ($data['secret'] ?? '')
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'user' => $this->user,
            'secret' => $this->secret,
        ];
    }

    public function isValid(): bool
    {
        return $this->name !== ''
            && $this->url !== ''
            && $this->user !== ''
            && $this->secret !== '';
    }
}
