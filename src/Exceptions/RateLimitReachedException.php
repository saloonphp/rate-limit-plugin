<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Exceptions;

use Saloon\RateLimiter\Limit;
use Saloon\Exceptions\SaloonException;

class RateLimitReachedException extends SaloonException
{
    /**
     * Constructor
     *
     * @param \Saloon\RateLimiter\Limit $limit
     */
    public function __construct(readonly protected Limit $limit)
    {
        parent::__construct(sprintf('Request Rate Limit Reached (Name: %s)', $this->limit->getName()));
    }

    /**
     * Get the limit that was reached
     *
     * @return \Saloon\RateLimiter\Limit
     */
    public function getLimit(): Limit
    {
        return $this->limit;
    }
}
