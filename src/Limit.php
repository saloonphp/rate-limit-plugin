<?php

declare(strict_types=1);

namespace Saloon\RateLimiter;

use Closure;
use Saloon\Helpers\Date;
use InvalidArgumentException;
use Saloon\Contracts\Response;
use Saloon\RateLimiter\Traits\HasIntervals;
use Saloon\RateLimiter\Exceptions\LimitException;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

class Limit
{
    use HasIntervals;

    /**
     * Name prefix
     *
     * @var string
     */
    protected string $prefix = 'saloon_rate_limiter';

    /**
     * Name of the limit
     *
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * Number of hits the limit has had
     *
     * @var int
     */
    protected int $hits = 0;

    /**
     * Number of requests that are allowed in the time period
     *
     * @var int
     */
    protected int $allow;

    /**
     * The threshold that should be used when determining if a limit has been reached
     *
     * Must be between 0 and 1. For example if you want the limiter to kick in at 85%
     * you must set the threshold to 0.85
     *
     * @var float
     */
    protected float $threshold = 1;

    /**
     * The expiry timestamp of the rate limiter. Used to determine how much longer
     * a limiter's hits should last.
     *
     * @var int|null
     */
    protected ?int $expiryTimestamp = null;

    /**
     * Determines if a limit has been manually exceeded.
     *
     * @var bool
     */
    protected bool $exceeded = false;

    /**
     * Custom response handler
     *
     * @var Closure|null
     */
    protected ?Closure $responseHandler = null;

    /**
     * Determines if we should sleep or not
     *
     * @var bool
     */
    protected bool $shouldSleep = false;

    /**
     * Constructor
     *
     * @param int $allow
     * @param float $threshold
     * @param callable|null $responseHandler
     */
    final public function __construct(int $allow, float $threshold = 1, callable $responseHandler = null)
    {
        $this->allow = $allow;
        $this->threshold = $threshold;
        $this->responseHandler = $responseHandler;
    }

    /**
     * Construct a limiter's allow and threshold
     *
     * @param int $requests
     * @param float $threshold
     * @return static
     */
    public static function allow(int $requests, float $threshold = 1): static
    {
        return new static($requests, $threshold);
    }

    /**
     * Construct a custom "fromResponse" limier
     *
     * @param callable $onResponse
     * @return static
     */
    public static function fromResponse(callable $onResponse): static
    {
        return (new static(1, 1, $onResponse(...)))->everySeconds(60, 'response');
    }

    /**
     * Detect when the response has a status of 429 and release by the number of seconds
     *
     * @param int $releaseInSeconds
     * @return static
     */
    public static function fromTooManyRequests(int $releaseInSeconds): static
    {
        return static::fromResponse(static function (Response $response, Limit $limit) use ($releaseInSeconds) {
            if ($response->status() === 429) {
                $limit->exceeded($releaseInSeconds);
            }
        });
    }

    /**
     * Check if the limit has been reached
     *
     * @param float|null $threshold
     * @return bool
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
     * @param int $amount
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
     *
     * @param int|null $releaseInSeconds
     * @return void
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
     *
     * @return int
     */
    public function getHits(): int
    {
        return $this->hits;
    }

    /**
     * Get the name of the limit
     *
     * @return string
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
     * @param string|null $name
     * @return $this
     */
    public function name(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the expiry timestamp
     *
     * @return int|null
     */
    public function getExpiryTimestamp(): ?int
    {
        return $this->expiryTimestamp ??= Date::now()->addSeconds($this->releaseInSeconds)->toDateTime()->getTimestamp();
    }

    /**
     * Set the expiry timestamp
     *
     * @param int|null $expiryTimestamp
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
     *
     * @return int
     */
    public function getRemainingSeconds(): int
    {
        return (int)round($this->getExpiryTimestamp() - $this->getCurrentTimestamp());
    }

    /**
     * Get the release time in seconds
     *
     * @return int
     */
    public function getReleaseInSeconds(): int
    {
        return $this->releaseInSeconds;
    }

    /**
     * Check if the limit has been exceeded
     *
     * @return bool
     */
    public function wasManuallyExceeded(): bool
    {
        return $this->exceeded;
    }

    /**
     * Validate the limit
     *
     * @return void
     */
    public function validate(): void
    {
        // Todo: Validate we have allow and releaseInSeconds
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
     *
     * @return bool
     */
    public function shouldSleep(): bool
    {
        return $this->shouldSleep;
    }

    /**
     * Check if the limit uses a response
     *
     * @return bool
     */
    public function usesResponse(): bool
    {
        return isset($this->responseHandler);
    }

    /**
     * Handle a response on the limit
     *
     * @param \Saloon\Contracts\Response $response
     * @return void
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
     * @param \Saloon\RateLimiter\Contracts\RateLimiterStore $store
     * @return $this
     * @throws \JsonException
     * @throws \Saloon\RateLimiter\Exceptions\LimitException
     */
    public function update(RateLimiterStore $store): static
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
     * @param \Saloon\RateLimiter\Contracts\RateLimiterStore $store
     * @param int $resetHits
     * @return $this
     * @throws \JsonException
     * @throws \Saloon\RateLimiter\Exceptions\LimitException
     */
    public function save(RateLimiterStore $store, int $resetHits = 1): static
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
     *
     * @return int
     */
    public function getAllow(): int
    {
        return $this->allow;
    }

    /**
     * Get the threshold allowed in the interval
     *
     * @return float
     */
    public function getThreshold(): float
    {
        return $this->threshold;
    }

    /**
     * Set the prefix
     *
     * @param string $prefix
     * @return Limit
     */
    public function setPrefix(string $prefix): Limit
    {
        $this->prefix = $prefix;

        return $this;
    }
}
