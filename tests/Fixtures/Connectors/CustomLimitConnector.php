<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Traits\HasRateLimiting;
use Saloon\RateLimitPlugin\Contracts\RateLimiterStore;

final class CustomLimitConnector extends Connector
{
    use HasRateLimiting;

    public function __construct(
        protected array $limits,
        protected RateLimiterStore $store,
    ) {
        //
    }

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
        return $this->limits;
    }

    /**
     * Resolve the rate limiter store to use
     */
    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        return $this->store;
    }
}
