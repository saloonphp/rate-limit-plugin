<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Helpers;

use Closure;
use Saloon\Contracts\Response;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Exceptions\LimitException;

class LimitHelper
{
    /**
     * Hydrate the limits
     *
     * @param array<Limit> $limits
     * @return array<Limit>
     * @throws LimitException
     */
    public static function configureLimits(array $limits, ?string $prefix, ?Closure $tooManyAttemptsHandler = null): array
    {
        // Firstly, we will clean up the limits array to only ensure the `Limit` classes
        // are being processed.

        $limits = array_filter($limits, static fn (mixed $value) => $value instanceof Limit);

        // Now we'll make a clone of each of the limits at their empty state
        // This is important because otherwise we'll be mutating the original
        // objects and keep adding to the same object instances.

        $limits = array_map(static fn (Limit $limit) => clone $limit, $limits);

        // Next we will append our "too many attempts" limit which will be used when
        // the response actually hits a 429 status.

        if (isset($tooManyAttemptsHandler)) {
            $limits[] = Limit::custom($tooManyAttemptsHandler)->name('too_many_attempts_limit');
        }

        // Next we will set the prefix on each of the limits.

        $limits = array_map(static fn (Limit $limit) => $limit->setPrefix($prefix), $limits);

        // Finally, we will check if there are any duplicate limits. If there are, then we will
        // throw an exception instead of continuing.

        if ($duplicateLimit = self::getDuplicate($limits)) {
            throw new LimitException(sprintf('Duplicate limit name "%s". Consider using a custom name on the limit.', $duplicateLimit));
        }

        return $limits;
    }

    /**
     * Get the first duplicate limit
     *
     * @param array<Limit> $limits
     */
    private static function getDuplicate(array $limits): ?string
    {
        $limitNames = array_map(static fn (Limit $limit) => $limit->getName(), $limits);

        foreach (array_count_values($limitNames) as $name => $count) {
            if ($count > 1) {
                return $name;
            }
        }

        return null;
    }
}
