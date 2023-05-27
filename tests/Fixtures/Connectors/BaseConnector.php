<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Http\Connector;

class BaseConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://tests.saloon.dev/api';
    }
}
