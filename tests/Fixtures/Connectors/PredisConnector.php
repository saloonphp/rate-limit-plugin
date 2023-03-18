<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Tests\Fixtures\Connectors;

use Predis\Client;
use Saloon\Http\Connector;
use Saloon\RateLimiter\Contracts\RateLimiterStore;
use Saloon\RateLimiter\HasRateLimiting;
use Saloon\RateLimiter\Limit;
use Saloon\RateLimiter\Stores\PredisStore;

final class PredisConnector extends Connector
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
     *
     * @return \Saloon\RateLimiter\Contracts\RateLimiterStore
     */
    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        return new PredisStore(new Client);
    }
}
