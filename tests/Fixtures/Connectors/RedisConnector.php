<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Tests\Fixtures\Connectors;

use Redis;
use Saloon\Http\Connector;
use Saloon\RateLimiter\Contracts\RateLimiterStore;
use Saloon\RateLimiter\Limit;
use Saloon\RateLimiter\Stores\RedisStore;
use Saloon\RateLimiter\Traits\HasRateLimiting;

final class RedisConnector extends Connector
{
    use HasRateLimiting;

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
            Limit::allow(20)->everyHour(),
            Limit::allow(20)->everyDayUntil('10:30pm'),
            Limit::allow(20)->untilEndOfMonth(),
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
}
