<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Traits;

use DateTimeImmutable;
use Saloon\Helpers\Date;

trait HasIntervals
{
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
     * Specify the number of seconds a limit will be released within
     *
     * @param int $seconds
     * @param string|null $timeToLiveKey
     * @return $this
     */
    public function everySeconds(int $seconds, ?string $timeToLiveKey = null): static
    {
        $this->releaseInSeconds = $seconds;
        $this->timeToLiveKey = $timeToLiveKey;

        return $this;
    }

    /**
     * Set the limit to be released every minute
     *
     * @return $this
     */
    public function everyMinute(): static
    {
        return $this->everySeconds(60);
    }

    /**
     * Set the limit to be released every five minutes
     *
     * @return $this
     */
    public function everyFiveMinutes(): static
    {
        return $this->everySeconds(60 * 5);
    }

    /**
     * Set the limit to be released every thirty minutes
     *
     * @return $this
     */
    public function everyThirtyMinutes(): static
    {
        return $this->everySeconds(60 * 30);
    }

    /**
     * Set the limit to be released every hour
     *
     * @return $this
     */
    public function everyHour(): static
    {
        return $this->everySeconds(60 * 60);
    }

    /**
     * Set the limit to be released every six hours
     *
     * @return $this
     */
    public function everySixHours(): static
    {
        return $this->everySeconds(60 * 60 * 6);
    }

    /**
     * Set the limit to be released every twelve hours
     *
     * @return $this
     */
    public function everyTwelveHours(): static
    {
        return $this->everySeconds(60 * 60 * 12);
    }

    /**
     * Set the limit to be released every day
     *
     * @return $this
     */
    public function everyDay(): static
    {
        return $this->everySeconds(60 * 60 * 24);
    }

    /**
     * Set the limit to be released at the end of the month
     *
     * @return $this
     */
    public function untilEndOfMonth(): static
    {
        $sundayTimestamp = (new DateTimeImmutable('last day of this month 23:59'))->getTimestamp();

        return $this->everySeconds(
            seconds: $sundayTimestamp - $this->getCurrentTimestamp(),
            timeToLiveKey: 'end_of_month'
        );
    }

    /**
     * Set the limit to be released every day
     *
     * @return $this
     * @throws \Exception
     */
    public function everyDayUntil(string $time): static
    {
        $timestamp = (new DateTimeImmutable($time))->getTimestamp();

        if ($timestamp < $this->getCurrentTimestamp()) {
            $timestamp = (new DateTimeImmutable('tomorrow ' . $time))->getTimestamp();
        }

        return $this->everySeconds(
            seconds: $timestamp - $this->getCurrentTimestamp(),
            timeToLiveKey: $time
        );
    }

    /**
     * Set the limit to be released at midnight on the current day
     *
     * @return $this
     */
    public function untilMidnightTonight(): static
    {
        $tomorrowTimestamp = (new DateTimeImmutable('tomorrow'))->getTimestamp();

        return $this->everySeconds(
            seconds: $tomorrowTimestamp - $this->getCurrentTimestamp(),
            timeToLiveKey: 'midnight'
        );
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
