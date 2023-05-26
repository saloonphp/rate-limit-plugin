<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

final class CustomLimitConnector extends Connector
{
    use HasRateLimits;

    public function __construct(
        protected RateLimitStore $store,
        protected array          $limits,
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
    protected function resolveRateLimitStore(): RateLimitStore
    {
        return $this->store;
    }
}
