<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Tests\Fixtures\Connectors;

use Redis;
use Saloon\Http\Connector;
use Saloon\RateLimiter\Limit;
use Saloon\RateLimiter\Stores\RedisStore;
use Saloon\RateLimiter\Traits\HasRateLimiting;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

final class RedisDestructConnector extends Connector
{
    use HasRateLimiting;

    public function __construct(public &$destructed = false)
    {
        //
    }

    public function resolveBaseUrl(): string
    {
        return 'https://tests.saloon.dev/api';
    }

    /**
     * Resolve the limits
     *
     * @return array
     * @throws \Exception
     */
    protected function resolveLimits(): array
    {
        return [
            Limit::allow(10)->everyMinute(),
        ];
    }

    /**
     * Resolve the rate limiter store to use
     *
     * @return \Saloon\RateLimiter\Contracts\RateLimiterStore
     * @throws \RedisException
     */
    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        $client = new Redis;
        $client->connect('127.0.0.1');

        return new RedisStore($client);
    }

    public function __destruct()
    {
        // This will test tht even with the middleware, we can still destruct the
        // object properly.

        $this->destructed = true;
    }
}
