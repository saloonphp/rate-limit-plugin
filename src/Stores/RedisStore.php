<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Stores;

use Redis;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

class RedisStore implements RateLimiterStore
{
    /**
     * Constructor
     *
     * @param \Redis $redis
     */
    public function __construct(readonly protected Redis $redis)
    {
        //
    }

    /**
     * Get a rate limit from the store
     *
     * @param string $key
     * @return string|null
     * @throws \RedisException
     */
    public function get(string $key): ?string
    {
        $data = $this->redis->get($key);

        return is_string($data) ? $data : null;
    }

    /**
     * Set the rate limit into the store
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return bool
     * @throws \RedisException
     */
    public function set(string $key, string $value, int $ttl): bool
    {
        return $this->redis->setex(
            key: $key,
            expire: $ttl,
            value: $value,
        );
    }
}
