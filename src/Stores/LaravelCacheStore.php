<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Stores;

use Illuminate\Contracts\Cache\Repository;
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
        $result = $this->store->put($key, $value, $ttl);

        // If the data already exists in the cache, then mysql returns 0 rows affected. Technically, this is not a
        // failure. We should check for this before returning anything and return true if it does exist.
        if ($result === false) {
            $existingValue = $this->store->get($key);
            if ($existingValue === $value) {
                return true;
            }
        }

        return $result;
    }
}
