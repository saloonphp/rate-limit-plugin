<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\PsrConnector;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\RedisConnector;
use Saloon\RateLimiter\Tests\Fixtures\Requests\UserRequest;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\FileConnector;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\PredisConnector;

test('it works with redis connector', function () {
    $connector = new RedisConnector;
    $request = new UserRequest;

    $connector->withMockClient(new MockClient([
        new MockResponse(['name' => 'Sam']),
    ]));

    $response = $connector->send($request);

    dd($response->json());
});

test('it works with predis connector', function () {
    $connector = new PredisConnector;
    $request = new UserRequest;

    $connector->withMockClient(new MockClient([
        new MockResponse(['name' => 'Sam']),
    ]));

    $response = $connector->send($request);

    dd($response->json());
});

test('it works with file connector', function () {
    $connector = new FileConnector;
    $request = new UserRequest;

    $connector->withMockClient(new MockClient([
        new MockResponse(['name' => 'Sam']),
    ]));

    $response = $connector->send($request);

    dd($response->json());
});

test('it works with psr connector', function () {
    $connector = new PsrConnector;
    $request = new UserRequest;

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam']),
    ]));

    for ($i = 0; $i < 10; $i++) {
        $response = $connector->send($request);
    }

    dd($connector->cache);

    // dd($response->json());
});

test('it works on a request', function () {
    //
});

test('it works on a solo request', function () {
    //
});
