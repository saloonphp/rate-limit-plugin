<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

use Saloon\Contracts\Response;
use Saloon\RateLimitPlugin\Limit;

final class DisabledTooManyRequestsConnector extends TestConnector
{
    protected bool $detectTooManyAttempts = false;
}
