<?php

declare(strict_types=1);

use Saloon\Enums\PipeOrder;
use Saloon\Http\PendingRequest;
use Saloon\RateLimitPlugin\Limit;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\FakeResponse;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Tests\Fixtures\Requests\UserRequest;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\TestConnector;

// Simulate what saloonphp/cache-plugin does: a request middleware with PipeOrder::FIRST
// returns a plain FakeResponse (not MockResponse). This mirrors CacheMiddleware exactly —
// it short-circuits the HTTP call, sets hasFakeResponse() on PendingRequest, and
// leaves isMocked() as false. DetermineMockResponse skips when hasFakeResponse() is already set.
function withCacheMiddleware(TestConnector $connector): void
{
    $connector->middleware()->onRequest(
        callable: function (PendingRequest $pendingRequest): FakeResponse {
            return new FakeResponse(['cached' => true], 200);
        },
        order: PipeOrder::FIRST,
    );
}

test('cache hits do not count against the rate limit', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    withCacheMiddleware($connector);

    // Send 10 requests — all served from "cache". Limit of 3 should never be reached.
    for ($i = 0; $i < 10; $i++) {
        $connector->send(new UserRequest);
    }

    expect($connector->hasReachedRateLimit())->toBeFalse();
});

test('cache hits do not increment the hit counter in the store', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    withCacheMiddleware($connector);

    $connector->send(new UserRequest);
    $connector->send(new UserRequest);
    $connector->send(new UserRequest);

    // No real hits recorded — store stays empty
    expect($store->getStore())->toBeEmpty();
});

test('real api responses still count against the rate limit', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    $connector->send(new UserRequest);
    $connector->send(new UserRequest);
    $connector->send(new UserRequest);

    expect($connector->hasReachedRateLimit())->toBeTrue();
});

test('cache hits do not throw when the rate limit is already exhausted by real requests', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    // Exhaust the limit with 3 real (mock) requests
    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    $connector->send(new UserRequest);
    $connector->send(new UserRequest);
    $connector->send(new UserRequest);

    expect($connector->hasReachedRateLimit())->toBeTrue();

    // Cache middleware must use PipeOrder::FIRST so it fires before the rate limit
    // onRequest check — mirroring how the real cache plugin registers its middleware.
    withCacheMiddleware($connector);

    expect(fn () => $connector->send(new UserRequest))
        ->not->toThrow(\Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException::class);
});

test('only real requests count toward the limit when mixed with cache hits', function () {
    $store = new MemoryStore;

    // 1 real request via mock client
    $connector = new TestConnector($store, [Limit::allow(3)->everyMinute()]);
    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));
    $connector->send(new UserRequest); // hits = 1

    // 5 cache hits via same store — should not add to the count
    withCacheMiddleware($connector);
    for ($i = 0; $i < 5; $i++) {
        $connector->send(new UserRequest);
    }

    // Only 1 real hit, limit of 3 not reached
    expect($connector->hasReachedRateLimit())->toBeFalse();
});
