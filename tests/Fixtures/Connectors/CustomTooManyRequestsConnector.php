<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Contracts\Response;
use Saloon\RateLimitPlugin\Limit;

final class CustomTooManyRequestsConnector extends TestConnector
{
    protected function handleTooManyAttempts(Response $response, Limit $limit): void
    {
        if ($response->json('error') === 'Too Many Attempts') {
            $limit->exceeded();
        }
    }
}
