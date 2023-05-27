<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Stores;

use Predis\Client;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

class PredisStore implements RateLimitStore
{
    /**
     * Constructor
     */
    public function __construct(protected Client $redis)
    {
        //
    }

    /**
     * Get a rate limit from the store
     */
    public function get(string $key): ?string
    {
        return $this->redis->get($key);
    }

    /**
     * Set the rate limit into the store
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
