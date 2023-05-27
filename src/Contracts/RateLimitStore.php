<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Contracts;

interface RateLimitStore
{
    /**
     * Get a rate limit from the store
     */
    public function get(string $key): ?string;

    /**
     * Set the rate limit into the store
     */
    public function set(string $key, string $value, int $ttl): bool;
}
