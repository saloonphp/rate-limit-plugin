<?php

declare(strict_types=1);

use Saloon\Contracts\Response;
use Saloon\RateLimitPlugin\Limit;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\RateLimitPlugin\Tests\Fixtures\Requests\UserRequest;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Saloon\RateLimitPlugin\Tests\Fixtures\Requests\LimitedRequest;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\BaseConnector;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\TestConnector;
use Saloon\Exceptions\Request\Statuses\InternalServerErrorException;
use Saloon\RateLimitPlugin\Tests\Fixtures\Requests\LimitedSoloRequest;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\CustomPrefixConnector;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\CustomTooManyRequestsConnector;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\DisabledTooManyRequestsConnector;

test('when making a request with the HasRateLimits trait added it will record the hits and throw exceptions', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    // Let's start by making sure our store is empty

    expect($store->getStore())->toBeEmpty();

    // We'll now send four requests. The first three will be fine, the fourth should throw an exception

    $currentTimestampPlusSixty = time() + 60;

    $responseA = $connector->send(new UserRequest);
    expect($responseA->status())->toBe(200);

    // We should now have two limits for our store. We should have one for the limit we've defined
    // as well as the limit for the "too many attempts" catcher.

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(2);
    expect($storeData)->toHaveKey('TestConnector:3_every_60');
    expect($storeData)->toHaveKey('TestConnector:too_many_attempts_limit');

    expect(parseRawLimit($storeData['TestConnector:3_every_60']))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['TestConnector:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    // Now let's make another request, and the hits for our limiter should increase but the too many attempts should not increase

    $responseB = $connector->send(new UserRequest);
    expect($responseB->status())->toBe(200);

    $storeData = $store->getStore();

    expect(parseRawLimit($storeData['TestConnector:3_every_60']))->toEqual([
        'hits' => 2,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['TestConnector:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    // Now let's make a third request

    $responseC = $connector->send(new UserRequest);
    expect($responseC->status())->toBe(200);

    $storeData = $store->getStore();

    expect(parseRawLimit($storeData['TestConnector:3_every_60']))->toEqual([
        'hits' => 3,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['TestConnector:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    // Finally, when we make the fourth request it should throw an exception

    $thrown = false;

    try {
        $connector->send(new UserRequest);
    } catch (RateLimitReachedException $exception) {
        $thrown = true;

        expect($exception->getMessage())->toEqual('Request Rate Limit Reached (Name: TestConnector:3_every_60)');
        expect($exception->getLimit())->toBeInstanceOf(Limit::class);
        expect($exception->getLimit()->getAllow())->toEqual(3);
        expect($exception->getLimit()->getReleaseInSeconds())->toEqual(60);
        expect($exception->getLimit()->getHits())->toEqual(3);
    }

    expect($thrown)->toBeTrue();
    expect($connector->hasReachedRateLimit())->toBeTrue();
});

test('when making a request with the HasRateLimits trait added it will record the hits and can sleep', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everySeconds(5)->sleep(),
    ]);

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    // We'll now send four requests. The first three will be fine, the fourth should throw an exception

    $currentTimestampPlusFive = time() + 5;

    $responseA = $connector->send(new UserRequest);
    expect($responseA->status())->toBe(200);

    // We should now have two limits for our store. We should have one for the limit we've defined
    // as well as the limit for the "too many attempts" catcher.

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(2);
    expect($storeData)->toHaveKey('TestConnector:3_every_5');
    expect($storeData)->toHaveKey('TestConnector:too_many_attempts_limit');

    expect(parseRawLimit($storeData['TestConnector:3_every_5']))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusFive,
    ]);

    // Now let's make another request, and the hits for our limiter should increase but the too many attempts should not increase

    $responseB = $connector->send(new UserRequest);
    expect($responseB->status())->toBe(200);

    $storeData = $store->getStore();

    expect(parseRawLimit($storeData['TestConnector:3_every_5']))->toEqual([
        'hits' => 2,
        'timestamp' => $currentTimestampPlusFive,
    ]);

    // Now let's make a third request

    $responseC = $connector->send(new UserRequest);
    expect($responseC->status())->toBe(200);

    $storeData = $store->getStore();

    expect(parseRawLimit($storeData['TestConnector:3_every_5']))->toEqual([
        'hits' => 3,
        'timestamp' => $currentTimestampPlusFive,
    ]);

    // Now when we make this request, it should pause the application for 10 seconds

    $connector->send(new UserRequest);

    expect(time())->toEqual($currentTimestampPlusFive);
});

test('you can create a limiter that listens for 429 and will automatically back off for the Retry-After duration', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everySeconds(5)->sleep(),
    ]);

    $limitKey = 'TestConnector:too_many_attempts_limit';

    expect($store->get($limitKey))->toBeNull();

    $connector->withMockClient(new MockClient([
        MockResponse::make(['name' => 'Sam', 'status' => 'Success']),
        MockResponse::make(['name' => 'Gareth', 'status' => 'Success']),
        MockResponse::make(['status' => 'Too Many Requests'], 429, ['Retry-After' => 500]),
    ]));

    $connector->send(new UserRequest);

    expect(parseRawLimit($store->get($limitKey)))->toHaveKey('hits', 0);

    $connector->send(new UserRequest);

    expect(parseRawLimit($store->get($limitKey)))->toHaveKey('hits', 0);

    $thrown = false;

    try {
        $connector->send(new UserRequest);
    } catch (RateLimitReachedException $exception) {
        $thrown = true;

        expect($exception->getMessage())->toEqual('Request Rate Limit Reached (Name: TestConnector:too_many_attempts_limit)');
        expect($exception->getLimit()->getRemainingSeconds())->toEqual(500);
    }

    expect($thrown)->toBeTrue();
});

test('if the Retry-After header is missing or cannot be parsed then the default retry is 60 seconds', function (mixed $retryAfter) {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everySeconds(5)->sleep(),
    ]);

    $connector->withMockClient(new MockClient([
        MockResponse::make(['name' => 'Sam', 'status' => 'Success']),
        MockResponse::make(['name' => 'Gareth', 'status' => 'Success']),
        MockResponse::make(['status' => 'Too Many Requests'], 429, ['Retry-After' => $retryAfter]),
    ]));

    $connector->send(new UserRequest);
    $connector->send(new UserRequest);

    $thrown = false;

    try {
        $connector->send(new UserRequest);
    } catch (RateLimitReachedException $exception) {
        $thrown = true;

        expect($exception->getMessage())->toEqual('Request Rate Limit Reached (Name: TestConnector:too_many_attempts_limit)');
        expect($exception->getLimit()->getRemainingSeconds())->toEqual(60);
    }

    expect($thrown)->toBeTrue();
})->with([
    null, 'not-working', '01/01/2023',
]);

test('you can customise when the too many requests limit is applied', function () {
    $store = new MemoryStore;

    $connector = new CustomTooManyRequestsConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connector->withMockClient(new MockClient([
        new MockResponse(['name' => 'Sam'], 200),
        new MockResponse(['name' => 'Sam'], 200),
        new MockResponse(['error' => 'Too Many Attempts'], 200),
    ]));

    $connector->send(new UserRequest);
    $connector->send(new UserRequest);

    $this->expectException(RateLimitReachedException::class);
    $this->expectExceptionMessage('Request Rate Limit Reached (Name: CustomTooManyRequestsConnector:too_many_attempts_limit)');

    $connector->send(new UserRequest);
});

test('you can disable the 429 error detection', function () {
    $store = new MemoryStore;
    $connector = new DisabledTooManyRequestsConnector($store, []);

    $connector->withMockClient(new MockClient([
        new MockResponse(['name' => 'Sam'], 200),
        new MockResponse(['name' => 'Sam'], 200),
        MockResponse::make(['status' => 'Too Many Requests'], 429),
    ]));

    $connector->send(new UserRequest);

    expect($store->getStore())->toBeEmpty();

    $connector->send(new UserRequest);
    $connector->send(new UserRequest);

    expect($store->getStore())->toBeEmpty();
});

test('the rate limiter can be used on a request', function () {
    $store = new MemoryStore;

    $connector = new BaseConnector();
    $connector->withMockClient(new MockClient([
        LimitedRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    // Let's start by making sure our store is empty

    expect($store->getStore())->toBeEmpty();

    // We'll now send four requests. The first three will be fine, the fourth should throw an exception

    $currentTimestampPlusSixty = time() + 60;

    // Now let's send the limited request

    $connector->send(new LimitedRequest($store));

    // We should now have two limits for our store. We should have one for the limit we've defined
    // as well as the limit for the "too many attempts" catcher.

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(2);
    expect($storeData)->toHaveKey('LimitedRequest:60_every_60');
    expect($storeData)->toHaveKey('LimitedRequest:too_many_attempts_limit');

    expect(parseRawLimit($storeData['LimitedRequest:60_every_60']))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['LimitedRequest:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);
});

test('when the the rate limiter is used on both the connector or request all the limits are used', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connector->withMockClient(new MockClient([
        LimitedRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    // Let's start by making sure our store is empty

    expect($store->getStore())->toBeEmpty();

    // We'll now send four requests. The first three will be fine, the fourth should throw an exception

    $currentTimestampPlusSixty = time() + 60;

    // Now let's send the limited request

    $connector->send(new LimitedRequest($store));

    // We should now have two limits for our store. We should have one for the limit we've defined
    // as well as the limit for the "too many attempts" catcher.

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(4);
    expect($storeData)->toHaveKey('LimitedRequest:60_every_60');
    expect($storeData)->toHaveKey('LimitedRequest:too_many_attempts_limit');
    expect($storeData)->toHaveKey('TestConnector:3_every_60');
    expect($storeData)->toHaveKey('TestConnector:too_many_attempts_limit');

    expect(parseRawLimit($storeData['TestConnector:3_every_60']))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['TestConnector:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['LimitedRequest:60_every_60']))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['LimitedRequest:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);
});

test('the rate limiter can be used on a solo request', function () {
    $store = new MemoryStore;

    $request = new LimitedSoloRequest($store);

    $request->withMockClient(new MockClient([
        LimitedSoloRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    // Let's start by making sure our store is empty

    expect($store->getStore())->toBeEmpty();

    // We'll now send four requests. The first three will be fine, the fourth should throw an exception

    $currentTimestampPlusSixty = time() + 60;

    // Now let's send the limited request

    $request->send();

    // We should now have two limits for our store. We should have one for the limit we've defined
    // as well as the limit for the "too many attempts" catcher.

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(2);
    expect($storeData)->toHaveKey('LimitedSoloRequest:60_every_60');
    expect($storeData)->toHaveKey('LimitedSoloRequest:too_many_attempts_limit');

    expect(parseRawLimit($storeData['LimitedSoloRequest:60_every_60']))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['LimitedSoloRequest:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);
});

test('you can specify a custom closure to determine the limiter based on response', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::custom(function (Response $response, Limit $limit) {
            if ($response->json('error') === 'Limit Reached') {
                $limit->exceeded(60);
            }
        }),
    ]);

    $connector->withMockClient(new MockClient([
        new MockResponse(['name' => 'Sam'], 200),
        new MockResponse(['name' => 'Sam'], 200),
        new MockResponse(['error' => 'Limit Reached'], 200),
    ]));

    $connector->send(new UserRequest);
    $connector->send(new UserRequest);

    $this->expectException(RateLimitReachedException::class);
    $this->expectExceptionMessage('Request Rate Limit Reached (Name: TestConnector:1_every_custom)');

    $connector->send(new UserRequest);
});

test('if a connector has the AlwaysThrowOnError trait then the limiter will take priority', function () {
    class ThrowConnector extends TestConnector
    {
        use AlwaysThrowOnErrors;
    }

    $store = new MemoryStore;

    $connector = new ThrowConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connector->withMockClient(new MockClient([
        new MockResponse(['name' => 'Sam'], 500),
    ]));

    $thrown = false;

    try {
        $connector->send(new UserRequest);
    } catch (InternalServerErrorException $exception) {
        $thrown = true;
    }

    expect($thrown)->toBeTrue();

    // If our middleware wasn't appended, then we wouldn't have these in here as it
    // would have thrown before the rate limiter had time to increment the limits.

    $storeData = $store->getStore();
    expect($storeData)->toHaveCount(2);
    expect($storeData)->toHaveKey('ThrowConnector:3_every_60');
    expect($storeData)->toHaveKey('ThrowConnector:too_many_attempts_limit');
});

test('the limit is given the correct prefix even with custom names', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        Limit::allow(3)->everyMinute(),
        Limit::allow(3)->everyMinute()->name('custom_name'),
    ]);

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    $connector->send(new UserRequest);

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(3);
    expect($storeData)->toHaveKey('TestConnector:3_every_60');
    expect($storeData)->toHaveKey('TestConnector:custom_name');
    expect($storeData)->toHaveKey('TestConnector:too_many_attempts_limit');
});

test('you can customise the prefix', function () {
    $store = new MemoryStore;

    $connector = new CustomPrefixConnector($store, [
        Limit::allow(3)->everyMinute(),
        Limit::allow(3)->everyMinute()->name('custom_name'),
    ]);

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    $connector->send(new UserRequest);

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(3);
    expect($storeData)->toHaveKey('custom:3_every_60');
    expect($storeData)->toHaveKey('custom:custom_name');
    expect($storeData)->toHaveKey('custom:too_many_attempts_limit');
});

test('you can programmatically disable the rate limiting', function () {
    $store = new MemoryStore;

    $connector = new CustomPrefixConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connector->useRateLimitPlugin(false);

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    expect($store->getStore())->toBeEmpty();

    $connector->send(new UserRequest);

    expect($store->getStore())->toBeEmpty();

    $connector->useRateLimitPlugin();

    $connector->send(new UserRequest);

    expect($store->getStore())->toHaveCount(2);
});

test('it will fail if you dont configure a limiter properly', function () {
    $store = new MemoryStore;

    $connector = new TestConnector($store, [
        new Limit(60),
    ]);

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    $connector->send(new UserRequest);

    $storeData = $store->getStore();

    expect($storeData)->toHaveCount(2);
    expect($storeData)->toHaveKey('TestConnector:60_every_0');
    expect($storeData)->toHaveKey('TestConnector:too_many_attempts_limit');
});
