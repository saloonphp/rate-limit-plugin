<?php

namespace Saloon\RateLimiter\Helpers;

class RetryAfterHelper
{
    /**
     * Parse the retry after header
     *
     * @param string|null $retryAfter
     * @return int|null
     */
    public static function parse(?string $retryAfter): ?int
    {
        if (is_null($retryAfter)) {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return (int)$retryAfter;
        }

        $parsedDate = strtotime($retryAfter);

        return $parsedDate !== false ? $parsedDate - time() : null;
    }
}
