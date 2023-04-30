<?php

namespace Saloon\RateLimiter\Stores;

use Saloon\RateLimiter\Contracts\RateLimiterStore;

class MemoryStore implements RateLimiterStore
{
    /**
     * Limiter Store
     *
     * @var array
     */
    protected array $store = [];

    /**
     * Get a rate limit from the store
     *
     * @param string $key
     * @return string|null
     */
    public function get(string $key): ?string
    {
        return $this->store[$key] ?? null;
    }

    /**
     * Set the rate limit into the store
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return bool
     */
    public function set(string $key, string $value, int $ttl): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    /**
     * Get the store
     *
     * @return array
     */
    public function getStore(): array
    {
        return $this->store;
    }
}
