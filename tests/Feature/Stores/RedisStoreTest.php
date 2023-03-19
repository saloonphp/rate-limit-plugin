<?php

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\RedisConnector;
use Saloon\RateLimiter\Tests\Fixtures\Requests\UserRequest;

test('it records and can check exceeded limits', function () {
    //
});

test('it works with redis connector', function () {
    $connector = new RedisConnector;
    $request = new UserRequest;

    $connector->withMockClient(new MockClient([
        new MockResponse(['name' => 'Sam']),
    ]));

    $response = $connector->send($request);

    dd($response->json());
});
