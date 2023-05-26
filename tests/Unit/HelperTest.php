<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Exceptions\LimitException;
use Saloon\RateLimitPlugin\Helpers\LimitHelper;
use Saloon\RateLimitPlugin\Helpers\RetryAfterHelper;
use Saloon\RateLimitPlugin\Limit;

test('the retry after helper can parse different values', function (?string $value, ?int $expected) {
    expect(RetryAfterHelper::parse($value))->toBe($expected);
})->with([
    [null, null],
    ['0', 0],
    ['120', 120],
    ['2030-01-01 00:00:00', fn () => 1893456000 - time()],
    ['Wed, 23 Oct 2030 06:28:00 GMT', fn () => 1918967280 - time()],
    ['Unknown', null],
]);

test('the limit helper clones the limits', function () {
    $limits = [
        Limit::allow(60)->everyMinute()->setPrefix('custom'),
    ];

    $configuredLimits = LimitHelper::configureLimits($limits, 'custom');

    expect($configuredLimits)->toHaveCount(1);

    // Equal but not the exact object because it's cloned

    expect($configuredLimits[0])->toEqual($limits[0]);
    expect($configuredLimits[0])->not->toBe($limits[0]);
});

test('the limit helper throws an exception if two of the same limits have the same name', function () {
    $limits = [
        Limit::allow(60)->everyMinute()->name('custom'),
        Limit::allow(60)->everyMinute()->name('custom'),
    ];

    $this->expectException(LimitException::class);
    $this->expectExceptionMessage('Duplicate limit name "custom:custom". Consider using a custom name on the limit.');

    LimitHelper::configureLimits($limits, 'custom');
});
