<?php

namespace Saloon\RateLimitPlugin\Tests\Fixtures\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Saloon\RateLimitPlugin\Helpers\ApiRateLimited;
use Saloon\RateLimitPlugin\Tests\Fixtures\Connectors\TestConnector;
use Saloon\RateLimitPlugin\Tests\Fixtures\Requests\UserRequest;

class ApiRateLimitedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ApiRateLimited];
    }

    public function __construct(protected TestConnector $connector)
    {
        //
    }

    public function handle(): void
    {
        $this->connector->send(new UserRequest);
    }
}
