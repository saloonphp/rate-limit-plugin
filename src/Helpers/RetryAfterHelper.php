<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Helpers;

class RetryAfterHelper
{
    /**
     * Parse the retry after header
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
