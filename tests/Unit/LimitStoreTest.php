<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\TestConnector;

test('you can save and update a limiter from the store', function () {
    //
});

test('if the stored limit does not contain the timestamp or hits then it will throw an exception while updating', function () {
    //
});

test('if the current timestamp is after the expiry then the limit will not update', function () {
    //
});

test('the rate limiter store instance is reused on the connector', function () {
    $connector = new TestConnector(new MemoryStore, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connectorStore = $connector->rateLimitStore();

    expect($connector->rateLimitStore())->toBe($connectorStore);
});
