<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Saloon\RateLimitPlugin\Traits\HasIntervals;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Exceptions\LimitException;

class Bucket extends Limit
{
    use HasIntervals;

    protected float $leakRate;
    protected int $capacity;
    protected float $currentLevel = 0;
    protected ?int $lastLeakTimestamp = null;
    protected int $leakCount = 1;
    protected int $leakSeconds = 1;

    /**
     * Create a new bucket with specified capacity
     */
    public static function capacity(int $capacity): static
    {
        $instance = new static($capacity, 1, null);
        $instance->capacity = $capacity;
        $instance->leakCount = 1;
        $instance->leakSeconds = 1;
        $instance->leakRate = 1;
        $instance->releaseInSeconds = $capacity;

        return $instance;
    }

    /**
     * Set how many tokens leak
     *
     * @return $this
     */
    public function leak(int $count): static
    {
        $this->leakCount = $count;
        $this->recalculate();

        return $this;
    }

    /**
     * Set leak time period in seconds
     *
     * @return $this
     */
    public function every(int $seconds): static
    {
        $this->leakSeconds = $seconds;
        $this->recalculate();

        return $this;
    }

    /**
     * Override HasIntervals trait method to work with bucket leak logic
     *
     * @return $this
     */
    public function everySeconds(int $seconds, ?string $timeToLiveKey = null): static
    {
        return $this->every($seconds);
    }

    /**
     * Recalculate leak rate based on current settings
     */
    protected function recalculate(): void
    {
        $this->leakRate = $this->leakCount / $this->leakSeconds;
        $this->releaseInSeconds = (int)ceil($this->capacity / $this->leakRate);
    }

    public function getName(): string
    {
        if (isset($this->name)) {
            return $this->prefix . ':' . $this->name;
        }

        return sprintf(
            '%s:bucket_cap%d_leak%d_per%ds',
            $this->prefix,
            $this->capacity,
            $this->leakCount,
            $this->leakSeconds
        );
    }

    protected function processLeak(): static
    {
        $currentTimestamp = $this->getCurrentTimestamp();

        if ($this->lastLeakTimestamp === null) {
            $this->lastLeakTimestamp = $currentTimestamp;

            return $this;
        }

        $elapsedSeconds = $currentTimestamp - $this->lastLeakTimestamp;

        if ($elapsedSeconds > 0) {
            $this->currentLevel = max(0, $this->currentLevel - ($elapsedSeconds * $this->leakRate));
            $this->lastLeakTimestamp = $currentTimestamp;
        }

        return $this;
    }

    public function hit(int $amount = 1): static
    {
        if ($this->wasManuallyExceeded()) {
            return $this;
        }

        $this->processLeak();
        $this->currentLevel += $amount;
        $this->hits = (int)ceil($this->currentLevel);

        return $this;
    }

    public function hasReachedLimit(?float $threshold = null): bool
    {
        $threshold ??= $this->threshold;

        if ($threshold < 0 || $threshold > 1) {
            throw new InvalidArgumentException('Threshold must be between 0 and 1 (e.g., 0.85 for 85%).');
        }

        $this->processLeak();

        return $this->currentLevel >= ($threshold * $this->capacity);
    }

    public function getRemainingSeconds(): int
    {
        $this->processLeak();

        if ($this->currentLevel < $this->capacity) {
            return 0;
        }

        return (int)ceil(1 / $this->leakRate);
    }

    public function resetLimit(): static
    {
        $this->currentLevel = 0;
        $this->hits = 0;
        $this->lastLeakTimestamp = $this->getCurrentTimestamp();
        $this->exceeded = false;
        $this->expiryTimestamp = null;

        return $this;
    }

    public function update(RateLimitStore $store): static
    {
        $storeData = $store->get($this->getName());

        if (empty($storeData)) {
            return $this;
        }

        $data = json_decode($storeData, true, 512, JSON_THROW_ON_ERROR);

        if (! isset($data['currentLevel'], $data['lastLeakTimestamp'])) {
            throw new LimitException('Store data missing required fields: currentLevel, lastLeakTimestamp');
        }

        $this->currentLevel = (float)$data['currentLevel'];
        $this->lastLeakTimestamp = (int)$data['lastLeakTimestamp'];
        
        // Restore leak rate if saved (for backwards compatibility)
        if (isset($data['leakRate'])) {
            $this->leakRate = (float)$data['leakRate'];
        }
        
        $this->processLeak();
        $this->hits = (int)ceil($this->currentLevel);

        if (isset($data['exceeded'], $data['timestamp']) && $data['exceeded']) {
            if ($this->getCurrentTimestamp() <= $data['timestamp']) {
                $this->exceeded = true;
                $this->expiryTimestamp = $data['timestamp'];
            }
        }

        return $this;
    }

    public function save(RateLimitStore $store, int $resetHits = 1): static
    {
        $this->processLeak();

        $data = [
            'currentLevel' => $this->currentLevel,
            'lastLeakTimestamp' => $this->lastLeakTimestamp ?? $this->getCurrentTimestamp(),
            'capacity' => $this->capacity,
            'leakRate' => $this->leakRate,
        ];

        if ($this->exceeded && $this->expiryTimestamp !== null) {
            $data['exceeded'] = true;
            $data['timestamp'] = $this->expiryTimestamp;
        }

        $ttl = $this->currentLevel > 0
            ? (int)ceil($this->currentLevel / $this->leakRate) + 60
            : 60;

        if (! $store->set($this->getName(), json_encode($data, JSON_THROW_ON_ERROR), $ttl)) {
            throw new LimitException('Store failed to save limit data.');
        }

        return $this;
    }

    public function exceeded(?int $releaseInSeconds = null): void
    {
        $this->exceeded = true;
        $this->currentLevel = $this->capacity;
        $this->hits = $this->capacity;

        if (isset($releaseInSeconds)) {
            $interval = DateInterval::createFromDateString($releaseInSeconds . ' seconds');

            if ($interval !== false) {
                $this->expiryTimestamp = (new DateTimeImmutable)->add($interval)->getTimestamp();
            }
        }
    }

    public function getLeakRate(): float
    {
        return $this->leakRate;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function getCurrentLevel(): float
    {
        $this->processLeak();

        return $this->currentLevel;
    }

    public function getLastLeakTimestamp(): ?int
    {
        return $this->lastLeakTimestamp;
    }
}
