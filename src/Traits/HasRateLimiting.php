<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Traits;

use Saloon\RateLimiter\Limit;
use Saloon\Contracts\Response;
use Saloon\Contracts\PendingRequest;
use Saloon\RateLimiter\Helpers\LimitHelper;
use Saloon\RateLimiter\Contracts\RateLimiterStore;
use Saloon\RateLimiter\Exceptions\RateLimitReachedException;

trait HasRateLimiting
{
    /**
     * Is Rate limiting is enabled?
     *
     * @var bool
     */
    protected bool $rateLimitingEnabled = true;

    /**
     * The rate limiter store
     *
     * @var \Saloon\RateLimiter\Contracts\RateLimiterStore|null
     */
    protected ?RateLimiterStore $rateLimiterStore = null;

    /**
     * Boot the has rate limiting trait
     *
     * @param \Saloon\Contracts\PendingRequest $pendingRequest
     * @return void
     */
    public function bootHasRateLimiting(PendingRequest $pendingRequest): void
    {
        if (! $this->rateLimitingEnabled) {
            return;
        }

        // Firstly, we'll register a request middleware that will check if we have
        // exceeded any limits already. If we have, then this middleware will stop
        // the request from being processed.

        $pendingRequest->middleware()->onRequest(function (PendingRequest $pendingRequest) {
            if ($limit = $this->getExceededLimit()) {
                $this->handleExceededLimit($limit, $pendingRequest);
            }
        }, prepend: true);

        $pendingRequest->middleware()->onResponse(function (Response $response) {
            $limits = LimitHelper::configureLimits($this->resolveLimits(), $this);
            $store = $this->getRateLimiterStore();

            $limitThatWasExceeded = null;

            // First we'll iterate over every limit class, and we'll check if the limit has
            // been reached. We'll increment each of the limits and continue with the
            // response.

            foreach ($limits as $limit) {
                // We'll update our limit from the store which should populate it with the
                // latest timestamp and hits.

                $limit->update($store);

                // Now we'll "hit" the limit which will increase the count
                // We won't hit if it's a from response limiter.

                $limit->usesResponse()
                    ? $limit->handleResponse($response)
                    : $limit->hit();

                // If our limit has been exceeded, we will assign the limit
                // that was exceeded. This will throw an exception.

                if ($limit->wasManuallyExceeded()) {
                    $limitThatWasExceeded = $limit;
                }

                // Finally, we'll commit the limit onto the store

                $limit->save($store);
            }

            // If a limit was previously exceeded this means that the manual
            // check to see if a response has hit the limit has come into
            // place. We should make sure to throw the exception here.

            if (isset($limitThatWasExceeded)) {
                $this->throwLimitException($limitThatWasExceeded);
            }
        }, prepend: true);
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

        $store = $this->getRateLimiterStore();

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

    /**
     * Handle the exceeded limit
     *
     * If the limit should wait, we will increment a delay - otherwise we will continue
     *
     * @throws \Saloon\RateLimiter\Exceptions\RateLimitReachedException
     */
    protected function handleExceededLimit(Limit $limit, PendingRequest $pendingRequest): void
    {
        if (! $limit->shouldSleep()) {
            $this->throwLimitException($limit);
        }

        $existingDelay = $pendingRequest->delay()->get() ?? 0;
        $remainingMilliseconds = $limit->getRemainingSeconds() * 1000;

        $pendingRequest->delay()->set($existingDelay + $remainingMilliseconds);
    }

    /**
     * Enable or disable the rate limiting functionality
     *
     * @param bool $enabled
     * @return $this
     */
    public function useRateLimiting(bool $enabled = true): static
    {
        $this->rateLimitingEnabled = $enabled;

        return $this;
    }

    /**
     * Get the rate limiter store
     *
     * @return \Saloon\RateLimiter\Contracts\RateLimiterStore
     */
    public function getRateLimiterStore(): RateLimiterStore
    {
        return $this->rateLimiterStore ?? $this->resolveRateLimiterStore();
    }
}
