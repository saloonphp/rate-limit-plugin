<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Tests\Fixtures\Connectors;

use Predis\Client;
use Psr\SimpleCache\CacheInterface;
use Redis;
use Saloon\Http\Connector;
use Saloon\RateLimiter\Limit;
use Saloon\RateLimiter\HasRateLimiting;
use Saloon\RateLimiter\Stores\PredisStore;
use Saloon\RateLimiter\Contracts\RateLimiterStore;
use Saloon\RateLimiter\Stores\PsrStore;
use Saloon\RateLimiter\Stores\RedisStore;
use Saloon\RateLimiter\Tests\Fixtures\Helpers\ArrayPsrCache;

final class PsrConnector extends Connector
{
    use HasRateLimiting;

    readonly public CacheInterface $cache;

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
        return new PsrStore($this->cache);
    }
}
