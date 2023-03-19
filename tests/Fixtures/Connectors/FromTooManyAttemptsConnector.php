<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimiter\Limit;
use Saloon\RateLimiter\Stores\PsrStore;
use Saloon\RateLimiter\Traits\HasRateLimiting;
use Saloon\RateLimiter\Contracts\RateLimiterStore;
use Saloon\RateLimiter\Tests\Fixtures\Helpers\ArrayPsrCache;

final class FromTooManyAttemptsConnector extends Connector
{
    use HasRateLimiting;

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
            Limit::fromTooManyRequests(60),
        ];
    }

    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        return new PsrStore($this->cache);
    }
}
