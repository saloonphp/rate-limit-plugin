<?php

namespace Saloon\RateLimiter\Stores;

use Illuminate\Contracts\Cache\Store;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

class LaravelCacheStore implements RateLimiterStore
{
    /**
     * Constructor
     */
    public function __construct(protected Store $store)
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
