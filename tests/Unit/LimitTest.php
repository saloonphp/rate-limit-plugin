<?php

declare(strict_types=1);

use Saloon\Helpers\Date;
use Saloon\RateLimitPlugin\Limit;

test('you can create a limiter and specify an allow and threshold', function () {
    $limiter = Limit::allow(100, 0.5)->everyMinute();

    expect($limiter->getReleaseInSeconds())->toEqual(60);
    expect($limiter->getAllow())->toEqual(100);
    expect($limiter->getThreshold())->toEqual(0.5);
});

test('you can use different intervals to specify seconds', function (Limit $limit, int $seconds) {
    expect($limit->getRemainingSeconds())->toEqual($seconds);
})->with([
    [fn () => Limit::allow(60)->everyMinute(), 60],
    [fn () => Limit::allow(60)->everyFiveMinutes(), 300],
    [fn () => Limit::allow(60)->everyThirtyMinutes(), 1800],
    [fn () => Limit::allow(60)->everyHour(), 3600],
    [fn () => Limit::allow(60)->everySixHours(), 21600],
    [fn () => Limit::allow(60)->everyTwelveHours(), 43200],
    [fn () => Limit::allow(60)->everyDay(), 86400],
]);

test('you can create a limiter until the end of the current month', function () {
    $endOfMonthTimestamp = (new DateTimeImmutable('last day of this month 23:59'))->getTimestamp();
    $seconds = $endOfMonthTimestamp - Date::now()->toDateTime()->getTimestamp();

    $limit = Limit::allow(60)->untilEndOfMonth();

    expect($limit->getReleaseInSeconds())->toEqual($seconds);
});

test('you can create a limiter until midnight', function () {
    $tomorrowTimestamp = (new DateTimeImmutable('tomorrow'))->getTimestamp();
    $seconds = $tomorrowTimestamp - Date::now()->toDateTime()->getTimestamp();

    $limit = Limit::allow(60)->untilMidnightTonight();

    expect($limit->getReleaseInSeconds())->toEqual($seconds);
});

test('you can create a limiter to release every day at a specific time', function () {
    $timestamp = (new DateTimeImmutable('01:00'))->getTimestamp();
    $currentTimestamp = Date::now()->toDateTime()->getTimestamp();

    if ($timestamp < $currentTimestamp) {
        $timestamp = (new DateTimeImmutable('tomorrow 01:00'))->getTimestamp();
    }

    $seconds = $timestamp - $currentTimestamp;

    $limit = Limit::allow(60)->everyDayUntil('01:00');

    expect($limit->getReleaseInSeconds())->toEqual($seconds);
});

test('if the expiry timestamp is empty then the current timestamp will be used', function () {
    $limit = Limit::allow(60)->everyMinute();

    expect($limit->getExpiryTimestamp())->toEqual(time() + 60);
});

test('you can get the remaining seconds left on the limiter', function () {
    $limit = Limit::allow(60)->everyMinute();

    expect($limit->getRemainingSeconds())->toEqual(60);
});
