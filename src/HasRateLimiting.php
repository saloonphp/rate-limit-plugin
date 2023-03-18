<?php

declare(strict_types=1);

namespace Saloon\RateLimiter;

use Saloon\Contracts\Response;
use Saloon\Contracts\PendingRequest;
use Saloon\RateLimiter\Helpers\LimitHelper;
use Saloon\RateLimiter\Contracts\RateLimiterStore;
use Saloon\RateLimiter\Exceptions\RateLimitReachedException;

trait HasRateLimiting
{
    /**
     * Boot the has rate limiting trait
     *
     * @param \Saloon\Contracts\PendingRequest $pendingRequest
     * @return void
     */
    public function bootHasRateLimiting(PendingRequest $pendingRequest): void
    {
        // Firstly, we'll register a request middleware that will check if we have
        // exceeded any limits already. If we have, then this middleware will stop
        // the request from being processed.

        $pendingRequest->middleware()->onRequest(function () {
            if ($limit = $this->getExceededLimit()) {
                $this->throwLimitException($limit);
            }
        });

        $pendingRequest->middleware()->onResponse(function (Response $response) {
            // First, we'll use our LimitHelper to configure the limits on the request/connector
            // this will populate the ID properly as well as setting the names if it needs to.

            $limits = LimitHelper::configureLimits($this->resolveLimits(), $this);
            $store = $this->resolveRateLimiterStore();

            $limitThatWasExceeded = null;

            // Now we'll iterate over every limit class, and we'll check if the limit has
            // been reached. We'll increment each of the limits and continue with the
            // response.

            foreach ($limits as $limit) {
                // Let's first update the limit from the store, this will set the timestamp
                // and the number of hits that has already happened.

                $usesResponse = $limit->usesResponse();

                $limit->update($store);

                if ($usesResponse === true) {
                    $limit->handleResponse($response);
                }

                // We'll make sure our limits haven't been exceeded yet - if they haven't then
                // we will run the `checkForTooManyAttempts` method.

                if (is_null($limitThatWasExceeded) && $usesResponse === false) {
                    $this->checkResponseForLimit($response, $limit);
                }

                if ($limit->hasExceeded()) {
                    $limitThatWasExceeded = $limit;
                }

                // Now we'll "hit" the limit which will increase the count
                // We won't hit if it's a from response limiter

                if ($usesResponse === false) {
                    $limit->hit();
                }

                // Next, we'll commit the limit onto the store

                $limit->save($store);
            }

            // If a limit was previously exceeded this means that the manual
            // check to see if a response has hit the limit has come into
            // place. We should make sure to throw the exception here.

            if (isset($limitThatWasExceeded)) {
                $this->throwLimitException($limitThatWasExceeded);
            }
        });
    }

    /**
     * Resolve the limits for the rate limiter
     *
     * @return array<\Saloon\RateLimiter\Limit>
     */
    abstract protected function resolveLimits(): array;

    /**
     * Resolve the rate limit store
     *
     * @return RateLimiterStore
     */
    abstract protected function resolveRateLimiterStore(): RateLimiterStore;

    /**
     * Process the limit, can be extended
     *
     * @param \Saloon\Contracts\Response $response
     * @param \Saloon\RateLimiter\Limit $limit
     * @return void
     */
    protected function checkResponseForLimit(Response $response, Limit $limit): void
    {
        if ($response->status() === 429) {
            $limit->exceeded();
        }
    }

    /**
     * Throw the limit exception
     *
     * @param \Saloon\RateLimiter\Limit $limit
     * @return void
     * @throws \Saloon\RateLimiter\Exceptions\RateLimitReachedException
     */
    protected function throwLimitException(Limit $limit): void
    {
        throw new RateLimitReachedException($limit);
    }

    /**
     * Get the first limit that has exceeded
     *
     * @param float|null $threshold
     * @return \Saloon\RateLimiter\Limit|null
     * @throws \JsonException
     * @throws \ReflectionException
     * @throws \Saloon\RateLimiter\Exceptions\LimitException
     */
    public function getExceededLimit(?float $threshold = null): ?Limit
    {
        $limits = LimitHelper::configureLimits($this->resolveLimits(), $this);

        if (empty($limits)) {
            return null;
        }

        $store = $this->resolveRateLimiterStore();

        foreach ($limits as $limit) {
            $limit->update($store);

            if ($limit->hasReachedLimit($threshold)) {
                return $limit;
            }
        }

        return null;
    }

    /**
     * Check if we have reached the rate limit
     *
     * @param float|null $threshold
     * @return bool
     * @throws \JsonException
     * @throws \ReflectionException
     * @throws \Saloon\RateLimiter\Exceptions\LimitException
     */
    public function hasReachedRateLimit(?float $threshold = null): bool
    {
        return $this->getExceededLimit($threshold) instanceof Limit;
    }
}
