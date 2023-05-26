<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\DestructConnector;

test('the connector can still be destructed properly', function () {
    $destructed = false;
    $connector = new DestructConnector($destructed);

    expect($destructed)->toBeFalse();

    unset($connector);

    expect($destructed)->toBeTrue();
});
