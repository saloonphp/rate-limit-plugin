<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin;

use Closure;
use Saloon\Helpers\Date;
use InvalidArgumentException;
use Saloon\Contracts\Response;
use Saloon\RateLimitPlugin\Traits\HasIntervals;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Exceptions\LimitException;

class Limit
{
    use HasIntervals;

    /**
     * Name prefix
     */
    protected string $prefix = 'saloon_rate_limiter';

    /**
     * Name of the limit
     */
    protected ?string $name = null;

    /**
     * Number of hits the limit has had
     */
    protected int $hits = 0;

    /**
     * Number of requests that are allowed in the time period
     */
    protected int $allow;

    /**
     * The threshold that should be used when determining if a limit has been reached
     *
     * Must be between 0 and 1. For example if you want the limiter to kick in at 85%
     * you must set the threshold to 0.85
     */
    protected float $threshold = 1;

    /**
     * The expiry timestamp of the rate limiter. Used to determine how much longer
     * a limiter's hits should last.
     */
    protected ?int $expiryTimestamp = null;

    /**
     * Determines if a limit has been manually exceeded.
     */
    protected bool $exceeded = false;

    /**
     * Custom response handler
     */
    protected ?Closure $responseHandler = null;

    /**
     * Determines if we should sleep or not
     */
    protected bool $shouldSleep = false;

    /**
     * Constructor
     */
    final public function __construct(int $allow, float $threshold = 1, callable $responseHandler = null)
    {
        $this->allow = $allow;
        $this->threshold = $threshold;
        $this->responseHandler = $responseHandler;
    }

    /**
     * Construct a limiter's allow and threshold
     */
    public static function allow(int $requests, float $threshold = 1): static
    {
        return new static($requests, $threshold);
    }

    /**
     * Construct a custom limier from the response
     */
    public static function custom(callable $responseHandler): static
    {
        return (new static(1, 1, $responseHandler(...)))->everySeconds(60, 'custom');
    }

    /**
     * Check if the limit has been reached
     */
    public function hasReachedLimit(?float $threshold = null): bool
    {
        $threshold ??= $this->threshold;

        if ($threshold < 0 || $threshold > 1) {
            throw new InvalidArgumentException('Threshold must be a float between 0 and 1. For example a 85% threshold would be 0.85.');
        }

        return $this->hits >= ($threshold * $this->allow);
    }

    /**
     * Hit the limit
     *
     * @return $this
     */
    public function hit(int $amount = 1): static
    {
        if (! $this->wasManuallyExceeded()) {
            $this->hits += $amount;
        }

        return $this;
    }

    /**
     * Set the limit as exceeded
     */
    public function exceeded(int $releaseInSeconds = null): void
    {
        $this->exceeded = true;

        $this->hits = $this->allow;

        if (isset($releaseInSeconds)) {
            $this->expiryTimestamp = Date::now()->addSeconds($releaseInSeconds)->toDateTime()->getTimestamp();
        }
    }

    /**
     * Get the hits
     */
    public function getHits(): int
    {
        return $this->hits;
    }

    /**
     * Get the name of the limit
     */
    public function getName(): string
    {
        if (isset($this->name)) {
            return $this->prefix . ':' . $this->name;
        }

        return sprintf('%s:%s_every_%s', $this->prefix, $this->allow, $this->timeToLiveKey ?? (string)$this->releaseInSeconds);
    }

    /**
     * Specify a custom name
     *
     * @return $this
     */
    public function name(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the expiry timestamp
     */
    public function getExpiryTimestamp(): ?int
    {
        return $this->expiryTimestamp ??= Date::now()->addSeconds($this->releaseInSeconds)->toDateTime()->getTimestamp();
    }

    /**
     * Set the expiry timestamp
     *
     * @return $this
     */
    public function setExpiryTimestamp(?int $expiryTimestamp): static
    {
        $this->expiryTimestamp = $expiryTimestamp;

        return $this;
    }

    /**
     * Reset the limit
     *
     * @return $this
     */
    public function resetLimit(): static
    {
        $this->expiryTimestamp = null;
        $this->hits = 0;
        $this->exceeded = false;

        return $this;
    }

    /**
     * Get the remaining time in seconds
     */
    public function getRemainingSeconds(): int
    {
        return (int)round($this->getExpiryTimestamp() - $this->getCurrentTimestamp());
    }

    /**
     * Get the release time in seconds
     */
    public function getReleaseInSeconds(): int
    {
        return $this->releaseInSeconds;
    }

    /**
     * Check if the limit has been exceeded
     */
    public function wasManuallyExceeded(): bool
    {
        return $this->exceeded;
    }

    /**
     * Wait until the release time instead of throwing an exception
     *
     * @return $this
     */
    public function sleep(): static
    {
        $this->shouldSleep = true;

        return $this;
    }

    /**
     * Checks if the limit should wait
     */
    public function getShouldSleep(): bool
    {
        return $this->shouldSleep;
    }

    /**
     * Check if the limit uses a response
     */
    public function usesResponse(): bool
    {
        return isset($this->responseHandler);
    }

    /**
     * Handle a response on the limit
     */
    public function handleResponse(Response $response): void
    {
        if (! $this->usesResponse()) {
            return;
        }

        call_user_func($this->responseHandler, $response, $this);
    }

    /**
     * Update the limit from the store
     *
     * @return $this
     * @throws \JsonException
     * @throws \Saloon\RateLimitPlugin\Exceptions\LimitException
     */
    public function update(RateLimitStore $store): static
    {
        $storeData = $store->get($this->getName());

        // We'll just ignore if the store doesn't contain anything. This can
        // happen if there isn't anything inside the store.

        if (empty($storeData)) {
            return $this;
        }

        $data = json_decode($storeData, true, 512, JSON_THROW_ON_ERROR);

        if (! isset($data['timestamp'], $data['hits'])) {
            throw new LimitException('Unable to unserialize the store data as it does not contain the timestamp or hits');
        }

        if (! isset($data['allow']) && $this->usesResponse()) {
            throw new LimitException('Unable to unserialize the store data as the fromResponse limiter requires the allow in the data');
        }

        $expiry = $data['timestamp'];
        $hits = $data['hits'];

        // If the current timestamp is past the expiry, then we shouldn't set any data
        // this will mean that the next value will be a fresh counter in the store
        // with a fresh timestamp. This is especially useful for the stores that
        // don't have a TTL like file store.

        if ($this->getCurrentTimestamp() > $expiry) {
            return $this;
        }

        // If our expiry hasn't passed, yet then we'll set the expiry timestamp
        // and, we'll also update the hits so the current instance has the
        // number of previous hits.

        $this->setExpiryTimestamp($expiry);
        $this->hit($hits);

        // If this is a fromResponse limiter then we should apply the "allow" which will
        // be useful to check if we have reached our rate limit

        if ($this->usesResponse()) {
            $this->allow = $data['allow'];
        }

        return $this;
    }

    /**
     * Save the limit into the store
     *
     * @return $this
     * @throws \JsonException
     * @throws \Saloon\RateLimitPlugin\Exceptions\LimitException
     */
    public function save(RateLimitStore $store, int $resetHits = 1): static
    {
        // We may attempt to save the limit just as the expiry timestamp
        // passes, so we need to check that the remaining seconds isn't
        // less than zero. If it is zero or if it's negative, we will
        // reset the limit completely and hit once.

        if ($this->getRemainingSeconds() < 1) {
            $this->resetLimit()->hit($resetHits);
        }

        $data = [
            'timestamp' => $this->getExpiryTimestamp(),
            'hits' => $this->getHits(),
        ];

        if ($this->usesResponse()) {
            $data['allow'] = $this->allow;
        }

        $successful = $store->set(
            key: $this->getName(),
            value: json_encode($data, JSON_THROW_ON_ERROR),
            ttl: $this->getRemainingSeconds(),
        );

        if ($successful === false) {
            throw new LimitException('The store was unable to update the limit.');
        }

        return $this;
    }

    /**
     * Get the number of requests allowed in the interval
     */
    public function getAllow(): int
    {
        return $this->allow;
    }

    /**
     * Get the threshold allowed in the interval
     */
    public function getThreshold(): float
    {
        return $this->threshold;
    }

    /**
     * Set the prefix
     */
    public function setPrefix(string $prefix): Limit
    {
        $this->prefix = $prefix;

        return $this;
    }
}
