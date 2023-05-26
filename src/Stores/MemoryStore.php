<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Stores;

use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

class MemoryStore implements RateLimitStore
{
    /**
     * Limiter Store
     *
     * @var array<string, mixed>
     */
    protected array $store = [];

    /**
     * Get a rate limit from the store
     */
    public function get(string $key): ?string
    {
        return $this->store[$key] ?? null;
    }

    /**
     * Set the rate limit into the store
     */
    public function set(string $key, string $value, int $ttl): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    /**
     * Get the store
     *
     * @return array<string, mixed>
     */
    public function getStore(): array
    {
        return $this->store;
    }
}
