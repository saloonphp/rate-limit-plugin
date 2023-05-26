<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\FileStore;

test('it records and can check exceeded limits', function () {
    $directory = getTestingDirectory();
    $store = new FileStore($directory);

    $timestamp = time();

    $limit = Limit::allow(60)->everyMinute()->setPrefix('custom')->name('limit')->setExpiryTimestamp($timestamp);
    $limit->hit();

    expect($limit->getHits())->toBe(1);
    expect($limit->getReleaseInSeconds())->toBe(60);

    // We'll first check if the store can handle empty stores

    expect($store->get('custom:limit'))->toBeNull();

    // Now we'll store the limit

    $limit->save($store);

    $rawFile = $store->get('custom:limit');

    expect(file_get_contents($directory . '/' . 'custom:limit'))->toEqual($rawFile);

    // Now we'll make sure the file looks correct

    expect($rawFile)->toEqual(json_encode([
        'timestamp' => $timestamp + 60,
        'hits' => 1,
    ]));
});
