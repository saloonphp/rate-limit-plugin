<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Helpers\RetryAfterHelper;

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
    //
});
