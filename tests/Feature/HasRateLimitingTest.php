<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\FileConnector;
use Saloon\RateLimiter\Tests\Fixtures\Connectors\PredisConnector;
use Saloon\RateLimiter\Tests\Fixtures\Requests\UserRequest;

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
