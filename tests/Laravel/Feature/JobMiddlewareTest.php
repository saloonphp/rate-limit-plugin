<?php

declare(strict_types=1);

use Saloon\RateLimitPlugin\Limit;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessed;
use Saloon\RateLimitPlugin\Stores\FileStore;
use Saloon\RateLimitPlugin\Tests\Fixtures\Requests\UserRequest;
use Saloon\RateLimitPlugin\Tests\Fixtures\Jobs\ApiRateLimitedJob;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\TestConnector;

test('the api rate limited job will release a job onto the queue if a limit is reached', function () {
    $store = new FileStore(getTestingDirectory());

    $connector = new TestConnector($store, [
        Limit::allow(3)->everyMinute(),
    ]);

    $connector->withMockClient(new MockClient([
        UserRequest::class => new MockResponse(['name' => 'Sam'], 200),
    ]));

    $jobs = [];

    Queue::after(static function (JobProcessed $event) use (&$jobs) {
        $jobs[] = $event->job;
    });

    ApiRateLimitedJob::dispatchSync($connector);
    ApiRateLimitedJob::dispatchSync($connector);
    ApiRateLimitedJob::dispatchSync($connector);
    ApiRateLimitedJob::dispatchSync($connector);

    expect($jobs)->toHaveCount(4);
    expect($jobs[0]->isReleased())->toBeFalse();
    expect($jobs[1]->isReleased())->toBeFalse();
    expect($jobs[2]->isReleased())->toBeFalse();
    expect($jobs[3]->isReleased())->toBeTrue();

    expect($connector->hasReachedRateLimit())->toBeTrue();
});
