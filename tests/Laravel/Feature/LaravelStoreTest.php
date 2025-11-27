<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;

test('it records and can check exceeded limits', function () {
    $cache = Cache::store('array');
    $store = new LaravelCacheStore($cache);

    $timestamp = time();

    $limit = Limit::allow(60)->everyMinute()->setPrefix('custom')->name('limit')->setExpiryTimestamp($timestamp);
    $limit->hit();

    expect($limit->getHits())->toBe(1);
    expect($limit->getReleaseInSeconds())->toBe(60);

    // We'll first check if the store can handle empty stores

    expect($store->get('custom:limit'))->toBeNull();

    // Now we'll store the limit

    $limit->save($store);

    $rawContents = $store->get('custom:limit');

    expect($cache->get('custom:limit'))->toEqual($rawContents);

    // Now we'll make sure the file looks correct

    expect($rawContents)->toEqual(json_encode([
        'timestamp' => $timestamp + 60,
        'hits' => 1,
    ]));
});

test('it handles MySQL upsert behavior returning false for identical data', function () {
    $mockCache = Mockery::mock(Repository::class);

    $key = 'test:limit';
    $value = json_encode(['timestamp' => time() + 60, 'hits' => 1]);

    $mockCache->shouldReceive('put')->with($key, $value, Mockery::any())->andReturn(false);
    $mockCache->shouldReceive('get')->with($key)->andReturn($value);

    $store = new LaravelCacheStore($mockCache);

    expect($store->set($key, $value, 60))->toBeTrue();
});
