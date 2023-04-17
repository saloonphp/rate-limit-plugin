<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Helpers;

use Saloon\Helpers\Arr;
use Saloon\Contracts\Request;
use Saloon\RateLimiter\Limit;
use Saloon\Contracts\Connector;
use Saloon\RateLimiter\Exceptions\LimitException;

class LimitHelper
{
    /**
     * Hydrate the limits
     *
     * @param array<\Saloon\RateLimiter\Limit> $limits
     * @param \Saloon\Contracts\Connector|\Saloon\Contracts\Request $connectorOrRequest
     * @return array<\Saloon\RateLimiter\Limit>
     * @throws \ReflectionException
     * @throws \Saloon\RateLimiter\Exceptions\LimitException
     */
    public static function configureLimits(array $limits, Connector|Request $connectorOrRequest): array
    {
        $limits = array_filter($limits, static fn (mixed $value) => $value instanceof Limit);

        if (empty($limits)) {
            return [];
        }

        // Todo: Remove setObjectName in favor of setting the name here if it has not already been set

        $limits = Arr::mapWithKeys($limits, static function (Limit $limit, int|string $key) use ($connectorOrRequest) {
            return [$key => is_string($key) ? $limit->name($key) : $limit->setObjectName($connectorOrRequest)];
        });

        $limitNames = array_map(static fn (Limit $limit) => $limit->getName(), $limits);

        foreach (array_count_values($limitNames) as $name => $count) {
            if ($count === 1) {
                continue;
            }

            throw new LimitException(sprintf('Duplicate limit name "%s". Consider adding a custom name to the limit.', $name));
        }

        return array_values($limits);
    }
}
