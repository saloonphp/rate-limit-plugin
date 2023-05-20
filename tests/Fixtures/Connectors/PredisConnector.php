<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Predis\Client;
use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\PredisStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimit;
use Saloon\RateLimitPlugin\Contracts\RateLimiterStore;

final class PredisConnector extends Connector
{
    use HasRateLimit;

    public function resolveBaseUrl(): string
    {
        return 'https://tests.saloon.dev/api';
    }

    /**
     * Resolve the limits
     */
    protected function resolveLimits(): array
    {
        return [
            Limit::allow(10)->everyMinute(),
            Limit::allow(20)->everyHour(),
        ];
    }

    /**
     * Resolve the rate limiter store to use
     */
    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        return new PredisStore(new Client);
    }
}
