<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimiter\Limit;
use Psr\SimpleCache\CacheInterface;
use Saloon\RateLimiter\Stores\PsrStore;
use Saloon\RateLimiter\Traits\HasRateLimiting;
use Saloon\RateLimiter\Contracts\RateLimiterStore;
use Saloon\RateLimiter\Tests\Fixtures\Helpers\ArrayPsrCache;

final class PsrConnector extends Connector
{
    use HasRateLimiting;

    public readonly CacheInterface $cache;

    public function __construct()
    {
        $this->cache = new ArrayPsrCache;
    }

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
        return new PsrStore($this->cache);
    }
}
