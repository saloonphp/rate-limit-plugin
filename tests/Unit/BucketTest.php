<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Bucket;

test('you can create a bucket and specify capacity', function () {
    $bucket = Bucket::capacity(100);

    expect($bucket->getCapacity())->toEqual(100);
    expect($bucket->getLeakRate())->toEqual(1.0); // Default: 1 token per second
    expect($bucket->getCurrentLevel())->toEqual(0.0);
});

test('you can set the leak count', function () {
    $bucket = Bucket::capacity(50)->leak(5);

    expect($bucket->getCapacity())->toEqual(50);
    expect($bucket->getLeakRate())->toEqual(5.0); // 5 tokens per second
});

test('you can set the leak period', function () {
    $bucket = Bucket::capacity(60)->leak(10)->every(2);

    expect($bucket->getCapacity())->toEqual(60);
    expect($bucket->getLeakRate())->toEqual(5.0); // 10 tokens per 2 seconds = 5 per second
});

test('you can chain capacity, leak, and every methods', function () {
    $bucket = Bucket::capacity(100)
        ->leak(25)
        ->every(5);

    expect($bucket->getCapacity())->toEqual(100);
    expect($bucket->getLeakRate())->toEqual(5.0); // 25 tokens per 5 seconds = 5 per second
});

test('bucket calculates leak rate correctly', function (int $capacity, int $leakCount, int $leakSeconds, float $expectedRate) {
    $bucket = Bucket::capacity($capacity)
        ->leak($leakCount)
        ->every($leakSeconds);

    expect($bucket->getLeakRate())->toEqual($expectedRate);
})->with([
    [100, 1, 1, 1.0],      // 1 per second
    [100, 10, 1, 10.0],    // 10 per second
    [100, 5, 2, 2.5],      // 5 per 2 seconds = 2.5 per second
    [100, 10, 5, 2.0],     // 10 per 5 seconds = 2 per second
    [100, 60, 60, 1.0],    // 60 per minute = 1 per second
]);

test('bucket name reflects its configuration', function () {
    $bucket = Bucket::capacity(50)
        ->leak(10)
        ->every(2);

    $name = $bucket->getName();

    expect($name)->toContain('bucket');
    expect($name)->toContain('cap50');
    expect($name)->toContain('leak10');
    expect($name)->toContain('per2s');
});

test('bucket can be named with custom name', function () {
    $bucket = Bucket::capacity(100)->name('my_custom_bucket');

    expect($bucket->getName())->toContain('my_custom_bucket');
});

test('bucket fills up when hit', function () {
    $bucket = Bucket::capacity(10);

    $bucket->hit();
    expect($bucket->getCurrentLevel())->toEqual(1.0);

    $bucket->hit(3);
    expect($bucket->getCurrentLevel())->toEqual(4.0);

    $bucket->hit(5);
    expect($bucket->getCurrentLevel())->toEqual(9.0);
});

test('bucket reaches limit when full', function () {
    $bucket = Bucket::capacity(5);

    expect($bucket->hasReachedLimit())->toBeFalse();

    $bucket->hit(5);
    expect($bucket->hasReachedLimit())->toBeTrue();
});

test('bucket respects threshold', function () {
    $bucket = Bucket::capacity(10);

    // At 80% threshold
    $bucket->hit(7);
    expect($bucket->hasReachedLimit(0.8))->toBeFalse();

    $bucket->hit(1);
    expect($bucket->hasReachedLimit(0.8))->toBeTrue(); // 8/10 = 80%
});

test('bucket can be reset', function () {
    $bucket = Bucket::capacity(10);

    $bucket->hit(5);
    expect($bucket->getCurrentLevel())->toEqual(5.0);

    $bucket->resetLimit();
    expect($bucket->getCurrentLevel())->toEqual(0.0);
});

test('bucket provides correct remaining seconds when full', function () {
    $bucket = Bucket::capacity(10)
        ->leak(5) // 5 tokens per second
        ->every(1);

    $bucket->hit(10); // Fill bucket

    // Should take 1/5 = 0.2 seconds to leak 1 token, rounded up to 1 second
    expect($bucket->getRemainingSeconds())->toEqual(1);
});

test('bucket returns zero remaining seconds when not full', function () {
    $bucket = Bucket::capacity(10);

    $bucket->hit(5); // Half full

    expect($bucket->getRemainingSeconds())->toEqual(0);
});

test('bucket getters return correct values', function () {
    $bucket = Bucket::capacity(20)
        ->leak(4)
        ->every(2);

    expect($bucket->getCapacity())->toEqual(20);
    expect($bucket->getLeakRate())->toEqual(2.0); // 4 per 2 seconds
    expect($bucket->getLastLeakTimestamp())->toBeNull();

    $bucket->hit();
    expect($bucket->getCurrentLevel())->toEqual(1.0);
    expect($bucket->getLastLeakTimestamp())->toBeInt();
});

test('bucket handles sleep mode', function () {
    $bucket = Bucket::capacity(10)->sleep();

    expect($bucket->getShouldSleep())->toBeTrue();
});

test('bucket can use threshold parameter in hasReachedLimit', function () {
    $bucket = Bucket::capacity(10);

    $bucket->hit(8); // 80% full

    expect($bucket->hasReachedLimit())->toBeFalse();      // Default threshold 1.0 (100%)
    expect($bucket->hasReachedLimit(0.75))->toBeTrue();   // 75% threshold
    expect($bucket->hasReachedLimit(0.85))->toBeFalse();  // 85% threshold
});

test('bucket throws exception with invalid threshold', function () {
    $bucket = Bucket::capacity(10);

    $bucket->hit(5);

    $bucket->hasReachedLimit(1.5); // > 1.0
})->throws(InvalidArgumentException::class, 'Threshold must be between 0 and 1');

test('bucket leaks tokens over time', function () {
    $bucket = Bucket::capacity(10)
        ->leak(1)  // 1 token per second
        ->every(1);

    $bucket->hit(5);
    expect($bucket->getCurrentLevel())->toEqual(5.0);

    // Simulate time passing by sleeping
    sleep(2);

    // Should have leaked ~2 tokens (may vary slightly due to timing)
    $level = $bucket->getCurrentLevel();
    expect($level)->toBeLessThan(5.0);
    expect($level)->toBeGreaterThanOrEqual(2.0);
});

test('bucket level never goes below zero', function () {
    $bucket = Bucket::capacity(10)
        ->leak(5)
        ->every(1);

    $bucket->hit(2);
    expect($bucket->getCurrentLevel())->toEqual(2.0);

    sleep(1); // Leak 5 tokens but only 2 exist

    expect($bucket->getCurrentLevel())->toEqual(0.0); // Should not go negative
});

test('bucket can handle fractional leak rates', function () {
    $bucket = Bucket::capacity(100)
        ->leak(1)
        ->every(3); // 1 token per 3 seconds = 0.333... per second

    expect($bucket->getLeakRate())->toBeGreaterThan(0.33);
    expect($bucket->getLeakRate())->toBeLessThan(0.34);
});

test('bucket works with custom prefix', function () {
    $bucket = Bucket::capacity(10)
        ->setPrefix('custom');

    expect($bucket->getName())->toStartWith('custom:');
});
