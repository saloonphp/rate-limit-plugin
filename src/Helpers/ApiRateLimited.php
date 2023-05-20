<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Helpers;

use Closure;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

class ApiRateLimited
{
    /**
     * Catch rate limits inside of jobs and release for the remaining seconds
     */
    public function handle(object $job, Closure $next): mixed
    {
        try {
            return $next($job);
        } catch (RateLimitReachedException $exception) {
            return $job->release($exception->getLimit()->getRemainingSeconds());
        }
    }
}
