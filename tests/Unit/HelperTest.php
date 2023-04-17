<?php

use Saloon\RateLimiter\Helpers\RetryAfterHelper;

test('the retry after helper can parse different values', function (string $value, ?int $expected) {
    expect(RetryAfterHelper::parse($value))->toBe($expected);
})->with([
    ['0', 0],
    ['120', 120],
    ['2030-01-01 00:00:00', fn () => 1893456000 - time()],
    ['Wed, 23 Oct 2030 06:28:00 GMT', fn () => 1918967280 - time()],
    ['Unknown', null],
]);
