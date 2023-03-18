<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Stores;

use Psr\SimpleCache\CacheInterface;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

class PsrStore implements RateLimiterStore
{
    /**
     * Constructor
     *
     * @param \Psr\SimpleCache\CacheInterface $cache
     */
    public function __construct(readonly protected CacheInterface $cache)
    {
        //
    }

    /**
     * Get a rate limit from the store
     *
     * @param string $key
     * @return string|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get(string $key): ?string
    {
        return $this->cache->get($key, null);
    }

    /**
     * Set the rate limit into the store
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return bool
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
