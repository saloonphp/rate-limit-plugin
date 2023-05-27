<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

test('it records and can check exceeded limits', function () {
    $store = new MemoryStore();

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

    expect($store->getStore()['custom:limit'])->toEqual($rawContents);

    // Now we'll make sure the file looks correct

    expect($rawContents)->toEqual(json_encode([
        'timestamp' => $timestamp + 60,
        'hits' => 1,
    ]));
});
