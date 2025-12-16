<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Limit;

class SleepTooManyRequestsConnector extends TestConnector
{
    protected function getTooManyAttemptsLimiter(): ?Limit
    {
        return Limit::custom($this->handleTooManyAttempts(...))->sleep();
    }

    /**
     * Handle too many attempts (429) statuses
     */
    protected function handleTooManyAttempts(Response $response, Limit $limit): void
    {
        if ($response->status() !== 429) {
            return;
        }

        $limit->exceeded(releaseInSeconds: 5);
    }
}
