<?php

namespace Saloon\RateLimiter\Helpers;

use Closure;
use Saloon\RateLimiter\Exceptions\RateLimitReachedException;

class LaravelRateLimitMiddleware
{
    /**
     * Catch rate limits inside of jobs and release for the remaining seconds
     *
     * @param object $job
     * @param Closure $next
     * @return mixed
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
