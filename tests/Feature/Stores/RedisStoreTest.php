<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Tests\Fixtures\Requests\UserRequest;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\RedisConnector;

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
