<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Contracts;

interface RateLimiterStore
{
    /**
     * Get a rate limit from the store
     *
     * @param string $key
     * @return string|null
     */
    public function get(string $key): ?string;

    /**
     * Set the rate limit into the store
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return bool
     */
    public function set(string $key, string $value, int $ttl): bool;
}
