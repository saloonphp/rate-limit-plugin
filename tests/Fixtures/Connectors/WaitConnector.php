<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\PsrStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimit;
use Saloon\RateLimitPlugin\Contracts\RateLimiterStore;
use Saloon\RateLimitPlugin\Tests\Fixtures\Helpers\ArrayPsrCache;

final class WaitConnector extends Connector
{
    use HasRateLimit;

    public readonly ArrayPsrCache $cache;

    public function __construct()
    {
        $this->cache = new ArrayPsrCache;
    }

    public function resolveBaseUrl(): string
    {
        return 'https://tests.saloon.dev/api';
    }

    protected function resolveLimits(): array
    {
        return [
            Limit::allow(1)->everySeconds(5)->sleep(),
        ];
    }

    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        return new PsrStore($this->cache);
    }
}
