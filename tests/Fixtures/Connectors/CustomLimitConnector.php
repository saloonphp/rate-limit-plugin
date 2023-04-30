<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Tests\Fixtures\Connectors;

use Redis;
use Saloon\Http\Connector;
use Saloon\RateLimiter\Limit;
use Saloon\RateLimiter\Stores\RedisStore;
use Saloon\RateLimiter\Traits\HasRateLimiting;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

final class CustomLimitConnector extends Connector
{
    use HasRateLimiting;

    public function __construct(
        protected array $limits,
        protected RateLimiterStore $store,
    )
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
        return $this->limits;
    }

    /**
     * Resolve the rate limiter store to use
     *
     * @return RateLimiterStore
     */
    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        return $this->store;
    }
}
