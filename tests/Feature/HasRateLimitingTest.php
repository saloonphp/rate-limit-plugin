<?php

declare(strict_types=1);

use Saloon\Helpers\Storage;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimiter\Exceptions\RateLimitReachedException;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\FromTooManyAttemptsConnector;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\WaitConnector;
use Saloon\RateLimiter\Tests\Fixtures\Requests\UserRequest;

test('when making a request with the HasRateLimiting trait added it will record the hits and throw exceptions', function () {

});

test('it works on a request', function () {
    //
});

test('it works on a solo request', function () {
    //
});

test('you can create a limiter that listens for 429 and will automatically back off', function () {
    $connector = new FromTooManyAttemptsConnector;
    $request = new UserRequest;

    $limitKey = 'FromTooManyAttemptsConnector_allow_1_every_response';

    expect($connector->cache->has($limitKey))->toBeFalse();

    $connector->withMockClient(new MockClient([
        MockResponse::make(['name' => 'Sam', 'status' => 'Success']),
        MockResponse::make(['name' => 'Gareth', 'status' => 'Success']),
        MockResponse::make(['status' => 'Too Many Requests'], 429),
    ]));

    $connector->send($request);

    expect(decodeStoreData($connector->cache->get($limitKey)))->toHaveKey('hits', 0);

    $connector->send($request);

    expect(decodeStoreData($connector->cache->get($limitKey)))->toHaveKey('hits', 0);

    $this->expectException(RateLimitReachedException::class);
    $this->expectExceptionMessage('Request Rate Limit Reached (Name: FromTooManyAttemptsConnector_allow_1_every_response)');

    $connector->send($request);
});

test('you can specify a custom closure to determine the limiter based on response', function () {
    // Todo: Make a mock client where the third request has a body status of "429" but the HTTP status is 200
});

test('you can create a limit that waits instead of throwing an error', function () {
    $connector = new WaitConnector;
    $request = new UserRequest;

    // The first request should send like normal, but the second request should wait
    // 5 seconds before continuing.

    $connector->send($request);

    $start = microtime(true);

    $connector->send($request);

    expect(round(microtime(true) - $start))->toBeGreaterThanOrEqual(5);
});
