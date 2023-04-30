<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimiter\Limit;
use Saloon\RateLimiter\Stores\MemoryStore;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\CustomLimitConnector;
use Saloon\RateLimiter\Tests\Fixtures\Requests\UserRequest;
use Saloon\RateLimiter\Exceptions\RateLimitReachedException;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\WaitConnector;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\FromTooManyAttemptsConnector;

test('when making a request with the HasRateLimiting trait added it will record the hits and throw exceptions', function () {
    $store = new MemoryStore;
    $connector = new CustomLimitConnector(
        limits: [
            Limit::allow(3)->everyMinute(),
        ],
        store: $store,
    );

    // Let's start by making sure our store is empty

    expect($store->getStore())->toBeEmpty();

    // We'll now send four requests. The first three will be fine, the fourth should throw an exception

    $currentTimestamp = time();

    $responseA = $connector->send(new UserRequest);
    expect($responseA->status())->toBe(200);

    $currentTimestampPlusSixty = $currentTimestamp + 60;

    // We should now have two limits for our store. We should have one for the limit we've defined
    // as well as the limit for the "too many attempts" catcher.

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(2)
        ->and($storeData)->toHaveKey('CustomLimitConnector:3_every_60')
        ->and($storeData)->toHaveKey('CustomLimitConnector:too_many_attempts_limit');

    expect(json_decode($storeData['CustomLimitConnector:3_every_60'], true))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(json_decode($storeData['CustomLimitConnector:too_many_attempts_limit'], true))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    // Now let's make another request, and the hits for our limiter should increase but the too many attempts should not increase

    $responseB = $connector->send(new UserRequest);
    expect($responseB->status())->toBe(200);

    $storeData = $store->getStore();

    dd($storeData);

    expect(json_decode($storeData['CustomLimitConnector:3_every_60'], true))->toEqual([
        'hits' => 2,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(json_decode($storeData['CustomLimitConnector:too_many_attempts_limit'], true))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    // Todo: Let's write up a test where we have a basic in-memory limit. We should limit it to 3 requests in

});

test('when making a request with the HasRateLimiting trait added it will record the hits and can sleep', function () {
    $store = new MemoryStore;
    $connector = new CustomLimitConnector(
        limits: [
            Limit::allow(3)->everyMinute(),
        ],
        store: $store,
    );

    // Todo: Let's write up a test where we have a basic in-memory limit. We should limit it to 3 requests in

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

test('you can create a limit that sleeps instead of throwing an error', function () {
    $connector = new WaitConnector;
    $request = new UserRequest;

    // The first request should send like normal, but
    // the second request should wait 5 seconds before
    // continuing.

    $connector->send($request);

    $start = microtime(true);

    $connector->send($request);

    expect(round(microtime(true) - $start))->toBeGreaterThanOrEqual(5);
});

test('if a connector has the AlwaysThrowOnError trait then the limiter will take priority', function () {
    // Todo: This tests the "prepend" on the HasRateLimiting middleware
});

test('if a limit has been reached then the request wont be sent and the limiter wont be updated', function () {
    // This tests the onRequest middleware
});

test('the limit is given the correct prefix', function () {
    //
});
