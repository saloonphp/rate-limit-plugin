<?php

declare(strict_types=1);

namespace Saloon\RateLimiter;

use Closure;
use ReflectionClass;
use DateTimeImmutable;
use Saloon\Helpers\Date;
use InvalidArgumentException;
use Saloon\Contracts\Request;
use Saloon\Contracts\Response;
use Saloon\Contracts\Connector;
use Saloon\RateLimiter\Exceptions\LimitException;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

class Limit
{
    /**
     * Connector string
     *
     * @var string
     */
    protected string $objectName;

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
     * The number of seconds it will take to release the rate limit after it has
     * been reached.
     *
     * @var int
     */
    protected int $releaseInSeconds;

    /**
     * Optional time to live key to specify the time in the default key.
     *
     * @var string|null
     */
    protected ?string $timeToLiveKey = null;

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
     * Constructor
     *
     * @param int $allow
     * @param float $threshold
     * @param callable|null $responseHandler
     */
    public function __construct(int $allow, float $threshold = 1, callable $responseHandler = null)
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
        return new self($requests, $threshold);
    }

    /**
     * Construct a custom "fromResponse" limier
     *
     * @param callable $onResponse
     * @return static
     */
    public static function fromResponse(callable $onResponse): static
    {
        return (new self(1, 1, $onResponse(...)))->everySeconds(120, 'response');
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

    public function hit(int $amount = 1): static
    {
        if (! $this->hasExceeded()) {
            $this->hits += $amount;
        }

        return $this;
    }

    public function exceeded($releaseInSeconds = null): void
    {
        $this->exceeded = true;

        $this->hits = $this->allow;

        if (isset($releaseInSeconds)) {
            $this->expiryTimestamp = Date::now()->addSeconds($releaseInSeconds)->toDateTime()->getTimestamp();
        }
    }

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
        return $this->name ?? sprintf('%s_allow_%s_every_%s', $this->objectName, $this->allow, $this->timeToLiveKey ?? (string)$this->releaseInSeconds);
    }

    /**
     * Specify a custom name
     *
     * @param string|null $name
     * @return $this
     */
    public function name(?string $name): Limit
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the object name for the default name
     *
     * @param \Saloon\Contracts\Connector|\Saloon\Contracts\Request $object
     * @return $this
     * @throws \ReflectionException
     */
    public function setObjectName(Connector|Request $object): Limit
    {
        $this->objectName = (new ReflectionClass($object::class))->getShortName();

        return $this;
    }

    /**
     * @return int|null
     */
    public function getExpiryTimestamp(): ?int
    {
        return $this->expiryTimestamp ??= Date::now()->addSeconds($this->releaseInSeconds)->toDateTime()->getTimestamp();
    }

    /**
     * @param int|null $expiryTimestamp
     * @return Limit
     */
    public function setExpiryTimestamp(?int $expiryTimestamp): static
    {
        $this->expiryTimestamp = $expiryTimestamp;

        return $this;
    }

    /**
     * Set the expiry timestamp from seconds
     *
     * @param int $seconds
     * @return $this
     */
    public function setExpiryTimestampFromSeconds(int $seconds): static
    {
        return $this->setExpiryTimestamp($this->getCurrentTimestamp() + $seconds);
    }

    public function everySeconds(int $seconds, ?string $timeToLiveKey = null): static
    {
        $this->releaseInSeconds = $seconds;
        $this->timeToLiveKey = $timeToLiveKey;

        return $this;
    }

    public function everyMinute(): static
    {
        return $this->everySeconds(60);
    }

    public function everyFiveMinutes(): static
    {
        return $this->everySeconds(60 * 5);
    }

    public function everyThirtyMinutes(): static
    {
        return $this->everySeconds(60 * 30);
    }

    public function everyHour(): static
    {
        return $this->everySeconds(60 * 60);
    }

    public function everySixHours(): static
    {
        return $this->everySeconds(60 * 60 * 6);
    }

    public function everyTwelveHours(): static
    {
        return $this->everySeconds(60 * 60 * 12);
    }

    public function everyDay(): static
    {
        return $this->everySeconds(60 * 60 * 24);
    }

    public function untilMidnightTonight(): static
    {
        $tomorrowTimestamp = (new DateTimeImmutable('tomorrow'))->getTimestamp();

        return $this->everySeconds(
            seconds: $tomorrowTimestamp - $this->getCurrentTimestamp(),
            timeToLiveKey: 'midnight'
        );
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

    public function getReleaseInSeconds(): int
    {
        return $this->releaseInSeconds;
    }

    public function hasExceeded(): bool
    {
        return $this->exceeded;
    }

    public function validate()
    {
        // Todo: Validate we have allow and releaseInSeconds
    }

    public function usesResponse(): bool
    {
        return isset($this->responseHandler);
    }

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
     * @return $this
     * @throws \JsonException
     * @throws \Saloon\RateLimiter\Exceptions\LimitException
     */
    public function save(RateLimiterStore $store): static
    {
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
     * Get the current timestamp
     *
     * @return int
     */
    protected function getCurrentTimestamp(): int
    {
        return Date::now()->toDateTime()->getTimestamp();
    }
}
