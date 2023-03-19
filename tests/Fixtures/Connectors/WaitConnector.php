<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimiter\Contracts\RateLimiterStore;
use Saloon\RateLimiter\Limit;
use Saloon\RateLimiter\Stores\FileStore;
use Saloon\RateLimiter\Stores\PsrStore;
use Saloon\RateLimiter\Tests\Fixtures\Helpers\ArrayPsrCache;
use Saloon\RateLimiter\Traits\HasRateLimiting;

final class WaitConnector extends Connector
{
    use HasRateLimiting;

    readonly public ArrayPsrCache $cache;

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
            Limit::allow(1)->everySeconds(5)->waitUntilRelease(),
        ];
    }

    protected function resolveRateLimiterStore(): RateLimiterStore
    {
        return new PsrStore($this->cache);
    }
}
