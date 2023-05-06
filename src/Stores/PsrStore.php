<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Stores;

use Psr\SimpleCache\CacheInterface;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

class PsrStore implements RateLimiterStore
{
    /**
     * Constructor
     */
    public function __construct(readonly protected CacheInterface $cache)
    {
        //
    }

    /**
     * Get a rate limit from the store
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get(string $key): ?string
    {
        return $this->cache->get($key, null);
    }

    /**
     * Set the rate limit into the store
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set(string $key, string $value, int $ttl): bool
    {
        return $this->cache->set(
            key: $key,
            value: $value,
            ttl: $ttl,
        );
    }
}
