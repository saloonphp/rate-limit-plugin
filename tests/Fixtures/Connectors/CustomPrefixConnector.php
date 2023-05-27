<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Connectors;

class CustomPrefixConnector extends TestConnector
{
    protected function getLimiterPrefix(): ?string
    {
        return 'custom';
    }
}
