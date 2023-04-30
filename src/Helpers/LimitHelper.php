<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Helpers;

use Closure;
use Saloon\RateLimiter\Limit;
use Saloon\Contracts\Response;
use Saloon\RateLimiter\Exceptions\LimitException;

class LimitHelper
{
    /**
     * Hydrate the limits
     *
     * @param array<Limit> $limits
     * @param string|null $prefix
     * @param \Closure $tooManyAttemptsHandler
     * @return array<Limit>
     * @throws \Saloon\RateLimiter\Exceptions\LimitException
     */
    public static function configureLimits(array $limits, ?string $prefix, Closure $tooManyAttemptsHandler): array
    {
        // Todo: Refactor this - I'm not a fan of all this logic being separate

        // Firstly, we will clean up the limits array to only ensure the `Limit` classes
        // are being processed.

        $limits = array_filter($limits, static fn (mixed $value) => $value instanceof Limit);

        // Next we will append our "too many attempts" limit which will be used when
        // the response actually hits a 429 status.

        $limits[] = Limit::fromResponse(static function (Response $response, Limit $limit) use ($tooManyAttemptsHandler) {
            if ($response->status() === 429) {
                $tooManyAttemptsHandler($response, $limit);
            }
        })->name('too_many_attempts_limit');

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
     * @return string|null
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
