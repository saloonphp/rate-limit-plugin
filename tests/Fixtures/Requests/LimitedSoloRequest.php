<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Requests;

use Saloon\Enums\Method;
use Saloon\Http\SoloRequest;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

final class LimitedSoloRequest extends SoloRequest
{
    use HasRateLimits;

    protected Method $method = Method::GET;

    public function __construct(
        protected RateLimitStore $store,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return 'https://tests.saloon.dev/api/user';
    }

    protected function resolveLimits(): array
    {
        return [
            Limit::allow(60)->everyMinute(),
        ];
    }

    protected function resolveRateLimitStore(): RateLimitStore
    {
        return $this->store;
    }
}
