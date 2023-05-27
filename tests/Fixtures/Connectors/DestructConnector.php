<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

final class DestructConnector extends Connector
{
    use HasRateLimits;

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
     */
    protected function resolveRateLimitStore(): RateLimitStore
    {
        return new MemoryStore;
    }

    public function __destruct()
    {
        // This will test tht even with the middleware, we can still destruct the
        // object properly.

        $this->destructed = true;
    }
}
