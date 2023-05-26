<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

class BaseConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://tests.saloon.dev/api';
    }
}
