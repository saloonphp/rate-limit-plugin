<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

final class DisabledTooManyRequestsConnector extends TestConnector
{
    protected bool $detectTooManyAttempts = false;
}
