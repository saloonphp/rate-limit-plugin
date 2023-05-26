<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Limit;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Tests\Fixtures\Requests\UserRequest;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\CustomLimitConnector;

test('when making a request with the HasRateLimits trait added it will record the hits and throw exceptions', function () {
    $store = new MemoryStore;

    $connector = new CustomLimitConnector($store, [
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
    expect($storeData)->toHaveKey('CustomLimitConnector:3_every_60');
    expect($storeData)->toHaveKey('CustomLimitConnector:too_many_attempts_limit');

    expect(parseRawLimit($storeData['CustomLimitConnector:3_every_60']))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['CustomLimitConnector:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    // Now let's make another request, and the hits for our limiter should increase but the too many attempts should not increase

    $responseB = $connector->send(new UserRequest);
    expect($responseB->status())->toBe(200);

    $storeData = $store->getStore();

    expect(parseRawLimit($storeData['CustomLimitConnector:3_every_60']))->toEqual([
        'hits' => 2,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['CustomLimitConnector:too_many_attempts_limit']))->toEqual([
        'hits' => 0,
        'allow' => 1,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    // Now let's make a third request

    $responseC = $connector->send(new UserRequest);
    expect($responseC->status())->toBe(200);

    $storeData = $store->getStore();

    expect(parseRawLimit($storeData['CustomLimitConnector:3_every_60']))->toEqual([
        'hits' => 3,
        'timestamp' => $currentTimestampPlusSixty,
    ]);

    expect(parseRawLimit($storeData['CustomLimitConnector:too_many_attempts_limit']))->toEqual([
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

        expect($exception->getMessage())->toEqual('Request Rate Limit Reached (Name: CustomLimitConnector:3_every_60)');
        expect($exception->getLimit())->toBeInstanceOf(Limit::class);
        expect($exception->getLimit()->getAllow())->toEqual(3);
        expect($exception->getLimit()->getReleaseInSeconds())->toEqual(60);
        expect($exception->getLimit()->getHits())->toEqual(3);
    }

    expect($thrown)->toBeTrue();
});

test('when making a request with the HasRateLimits trait added it will record the hits and can sleep', function () {
    $store = new MemoryStore;

    $connector = new CustomLimitConnector($store, [
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
    expect($storeData)->toHaveKey('CustomLimitConnector:3_every_5');
    expect($storeData)->toHaveKey('CustomLimitConnector:too_many_attempts_limit');

    expect(parseRawLimit($storeData['CustomLimitConnector:3_every_5']))->toEqual([
        'hits' => 1,
        'timestamp' => $currentTimestampPlusFive,
    ]);

    // Now let's make another request, and the hits for our limiter should increase but the too many attempts should not increase

    $responseB = $connector->send(new UserRequest);
    expect($responseB->status())->toBe(200);

    $storeData = $store->getStore();

    expect(parseRawLimit($storeData['CustomLimitConnector:3_every_5']))->toEqual([
        'hits' => 2,
        'timestamp' => $currentTimestampPlusFive,
    ]);

    // Now let's make a third request

    $responseC = $connector->send(new UserRequest);
    expect($responseC->status())->toBe(200);

    $storeData = $store->getStore();

    expect(parseRawLimit($storeData['CustomLimitConnector:3_every_5']))->toEqual([
        'hits' => 3,
        'timestamp' => $currentTimestampPlusFive,
    ]);

    // Now when we make this request, it should pause the application for 10 seconds

    $connector->send(new UserRequest);

    expect(time())->toEqual($currentTimestampPlusFive);
});

test('you can create a limiter that listens for 429 and will automatically back off for the Retry-After duration', function () {
    $store = new MemoryStore;

    $connector = new CustomLimitConnector($store, [
        Limit::allow(3)->everySeconds(5)->sleep(),
    ]);

    $limitKey = 'CustomLimitConnector:too_many_attempts_limit';

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

        expect($exception->getMessage())->toEqual('Request Rate Limit Reached (Name: CustomLimitConnector:too_many_attempts_limit)');
        expect($exception->getLimit()->getRemainingSeconds())->toEqual(500);
    }

    expect($thrown)->toBeTrue();
});

test('if the Retry-After header is missing or cannot be parsed then the default retry is 60 seconds', function (mixed $retryAfter) {
    $store = new MemoryStore;

    $connector = new CustomLimitConnector($store, [
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

        expect($exception->getMessage())->toEqual('Request Rate Limit Reached (Name: CustomLimitConnector:too_many_attempts_limit)');
        expect($exception->getLimit()->getRemainingSeconds())->toEqual(60);
    }

    expect($thrown)->toBeTrue();
})->with([
    null, 'not-working', '01/01/2023',
]);

test('you can customise when the too many requests limit is applied', function () {
    //
});

test('the rate limiter can be used on a request', function () {
    //
});

test('when the the rate limiter is used on both the connector or request the request takes priority', function () {
    //
});

test('the rate limiter can be used on a solo request', function () {
    //
});

test('you can specify a custom closure to determine the limiter based on response', function () {
    // Todo: Make a mock client where the third request has a body status of "429" but the HTTP status is 200
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
