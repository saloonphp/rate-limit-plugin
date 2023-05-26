<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\PsrStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Tests\Fixtures\Helpers\ArrayPsrCache;

final class FromTooManyAttemptsConnector extends Connector
{
    use HasRateLimits;

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
            // Limit::fromTooManyRequests(60),
        ];
    }

    protected function resolveRateLimitStore(): RateLimitStore
    {
        return new PsrStore($this->cache);
    }
}
