<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Stores;

use Predis\Client;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

class PredisStore implements RateLimiterStore
{
    /**
     * Constructor
     *
     * @param \Predis\Client $redis
     */
    public function __construct(protected Client $redis)
    {
        //
    }

    /**
     * Get a rate limit from the store
     *
     * @param string $key
     * @return string|null
     */
    public function get(string $key): ?string
    {
        return $this->redis->get($key);
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
        $status = $this->redis->setex(
            key: $key,
            seconds: $ttl,
            value: $value
        );

        return $status->getPayload() === 'OK';
    }
}
