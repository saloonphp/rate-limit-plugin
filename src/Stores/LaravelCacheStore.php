<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Stores;

use Illuminate\Cache\Repository;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

class LaravelCacheStore implements RateLimitStore
{
    /**
     * Constructor
     */
    public function __construct(protected Repository $store)
    {
        //
    }

    /**
     * Get a rate limit from the store
     */
    public function get(string $key): ?string
    {
        $data = $this->store->get($key);

        return is_string($data) ? $data : null;
    }

    /**
     * Set the rate limit into the store
     */
    public function set(string $key, string $value, int $ttl): bool
    {
        return $this->store->put($key, $value, $ttl);
    }
}
