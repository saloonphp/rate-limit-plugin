<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Redis;
use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\RedisStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimit;
use Saloon\RateLimitPlugin\Contracts\RateLimiterStore;

final class RedisConnector extends Connector
{
    use HasRateLimit;

    public function resolveBaseUrl(): string
    {
        return 'https://tests.saloon.dev/api';
    }

    /**
     * Resolve the limits
     *
     * @throws \Exception
     */
    protected function resolveLimits(): array
    {
        return [
            Limit::allow(10)->everyMinute(),
            // Limit::allow(20)->everyHour(),
            // Limit::allow(100)->everyDayUntil('10:30pm'),
        ];
    }

    /**
     * Resolve the rate limiter store to use
     *
     * @throws \RedisException
     */
    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        $client = new Redis;
        $client->connect('127.0.0.1');

        return new RedisStore($client);
    }
}
