<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Exceptions\LimitException;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\TestConnector;

test('the rate limiter store instance is reused on the connector', function () {
    $connector = new TestConnector(new MemoryStore, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connectorStore = $connector->rateLimitStore();

    expect($connector->rateLimitStore())->toBe($connectorStore);
});

test('if the limit does not contain the timestamp or hits then an exception is thrown while updating the limit', function () {
    $store = new MemoryStore;
    $store->set('custom:limit', json_encode(['foo' => 'bar']), 60);

    $limit = Limit::allow(60)->everyMinute()->setPrefix('custom')->name('limit');

    $this->expectException(LimitException::class);
    $this->expectExceptionMessage('Unable to unserialize the store data as it does not contain the timestamp or hits');

    $limit->update($store);
});

test('if a custom limit does not contain the allow key then an exception is thrown while updating the limit', function () {
    $store = new MemoryStore;
    $store->set('custom:limit', json_encode(['timestamp' => time(), 'hits' => 0]), 60);

    $limit = Limit::custom(fn () => null)->setPrefix('custom')->name('limit');

    $this->expectException(LimitException::class);
    $this->expectExceptionMessage('Unable to unserialize the store data as the fromResponse limiter requires the allow in the data');

    $limit->update($store);
});

test('if the current timestamp is after the expiry then the limit will not update', function () {
    $limit = Limit::allow(60)
        ->everyMinute()
        ->setPrefix('custom')
        ->name('limit');

    $store = new MemoryStore;
    $store->set('custom:limit', json_encode(['timestamp' => time() - 60, 'hits' => 60]), 60);

    $limit->update($store);

    expect($limit->getHits())->toBe(0);
});

test('when saving the limit if the expiry time is less than a second away then the hits will be set back to 1', function () {
    $currentTime = time();

    $store = new MemoryStore;
    $store->set('custom:limit', json_encode(['timestamp' => $currentTime + 60, 'hits' => 60]), 60);

    $limit = Limit::allow(60)
        ->everyMinute()
        ->setPrefix('custom')
        ->name('limit')
        ->setExpiryTimestamp($currentTime);

    $limit->update($store);

    expect($limit->getHits())->toEqual(60);

    $limit->hit();

    expect($limit->getHits())->toEqual(61);

    // Now we'll set the expiry timestamp to now and see if it saves

    $limit->setExpiryTimestamp($currentTime);

    $limit->save($store);

    expect($limit->getHits())->toEqual(1);

    // Now we'll see how many hits are in the store

    expect(parseRawLimit($store->get('custom:limit')))->toEqual([
        'timestamp' => $currentTime + 60,
        'hits' => 1,
    ]);
});
