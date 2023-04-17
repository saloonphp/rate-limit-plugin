<?php

use Saloon\RateLimiter\Limit;

test('you can create a limiter and specify an allow and threshold', function () {
    $limiter = Limit::allow(100, 0.5)->everyMinute();

    expect($limiter->getReleaseInSeconds())->toEqual(60);
    expect($limiter->getAllow())->toEqual(100);
    expect($limiter->getThreshold())->toEqual(0.5);
});

test('you can use different intervals to specify seconds', function () {
    //
});

test('you can validate a limiter', function () {
    //
});

test('if the expiry timestamp is empty then the current timestamp will be used', function () {
    //
});

test('you can get the remaining seconds left on the limiter', function () {
    //
});

