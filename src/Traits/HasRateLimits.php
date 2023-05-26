<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Traits;

use Exception;
use ReflectionClass;
use Saloon\Contracts\Response;
use Saloon\RateLimitPlugin\Exceptions\LimitException;
use Saloon\RateLimitPlugin\Limit;
use Saloon\Contracts\PendingRequest;
use Saloon\RateLimitPlugin\Helpers\LimitHelper;
use Saloon\RateLimitPlugin\Helpers\RetryAfterHelper;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

trait HasRateLimits
{
    /**
     * Is Rate limiting is enabled?
     */
    protected bool $rateLimitingEnabled = true;

    /**
     * The rate limiter store
     */
    protected ?RateLimitStore $rateLimitStore = null;

    /**
     * Attempt to automatically detect 429 errors
     */
    protected bool $detectTooManyAttempts = true;

    /**
     * Boot the has rate limiting trait
     */
    public function bootHasRateLimits(PendingRequest $pendingRequest): void
    {
        if (! $this->rateLimitingEnabled) {
            return;
        }

        // Firstly, we'll register a request middleware that will check if we have
        // exceeded any limits already. If we have, then this middleware will stop
        // the request from being processed.

        $pendingRequest->middleware()->onRequest(function (PendingRequest $pendingRequest): void {
            $limit = $this->getExceededLimit();

            if ($limit instanceof Limit) {
                $this->handleExceededLimit($limit, $pendingRequest);
            }
        }, prepend: true);

        $pendingRequest->middleware()->onResponse(function (Response $response): void {
            $limitThatWasExceeded = null;
            $store = $this->rateLimitStore();

            // First we'll iterate over every limit class, and we'll check if the limit has
            // been reached. We'll increment each of the limits and continue with the
            // response.

            $limits = $this->getLimits();

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
     * Get the prefix added to every limit
     */
    protected function getLimiterPrefix(): ?string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    /**
     * Handle too many attempts (429) statuses
     */
    protected function handleTooManyAttempts(Response $response, Limit $limit): void
    {
        if ($response->status() !== 429) {
            return;
        }

        $limit->exceeded(
            releaseInSeconds: RetryAfterHelper::parse($response->header('Retry-After')),
        );
    }

    /**
     * Throw the limit exception
     *
     * @throws \Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException
     */
    protected function throwLimitException(Limit $limit): void
    {
        throw new RateLimitReachedException($limit);
    }

    /**
     * Get the first limit that has exceeded
     *
     * @throws \JsonException
     * @throws LimitException
     * @throws Exception
     */
    public function getExceededLimit(?float $threshold = null): ?Limit
    {
        $store = $this->rateLimitStore();

        foreach ($this->getLimits() as $limit) {
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
     * @throws \JsonException
     * @throws LimitException
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
     * @throws \Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException
     */
    protected function handleExceededLimit(Limit $limit, PendingRequest $pendingRequest): void
    {
        if (! $limit->getShouldSleep()) {
            $this->throwLimitException($limit);
        }

        $existingDelay = $pendingRequest->delay()->get() ?? 0;
        $remainingMilliseconds = $limit->getRemainingSeconds() * 1000;

        $pendingRequest->delay()->set($existingDelay + $remainingMilliseconds);
    }

    /**
     * Enable or disable the rate limiting functionality
     *
     * @return $this
     */
    public function useRateLimiting(bool $enabled = true): static
    {
        // Todo: Consider renaming method name

        $this->rateLimitingEnabled = $enabled;

        return $this;
    }

    /**
     * Get the rate limiter store
     */
    public function rateLimitStore(): RateLimitStore
    {
        return $this->rateLimitStore ??= $this->resolveRateLimitStore();
    }

    /**
     * Get the limits for the rate limiter store
     *
     * @return array
     * @throws LimitException
     * @throws Exception
     */
    public function getLimits(): array
    {
        $tooManyAttemptsHandler = $this->detectTooManyAttempts === true ? $this->handleTooManyAttempts(...) : null;

        return LimitHelper::configureLimits($this->resolveLimits(), $this->getLimiterPrefix(), $tooManyAttemptsHandler);
    }

    /**
     * Resolve the limits for the rate limiter
     *
     * @return array<\Saloon\RateLimitPlugin\Limit>
     */
    abstract protected function resolveLimits(): array;

    /**
     * Resolve the rate limit store
     */
    abstract protected function resolveRateLimitStore(): RateLimitStore;
}
