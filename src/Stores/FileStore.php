<?php

declare(strict_types=1);

namespace Saloon\RateLimitPlugin\Stores;

use Throwable;
use Saloon\Helpers\Storage;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

class FileStore implements RateLimitStore
{
    /**
     * Storage Driver
     */
    protected readonly Storage $storage;

    /**
     * Constructor
     *
     * @throws \Saloon\Exceptions\DirectoryNotFoundException
     * @throws \Saloon\Exceptions\UnableToCreateDirectoryException
     */
    public function __construct(readonly protected string $directory)
    {
        $this->storage = new Storage($this->directory, false);
    }

    /**
     * Get a rate limit from the store
     */
    public function get(string $key): ?string
    {
        try {
            $data = $this->storage->get($key);
        } catch (Throwable $exception) {
            return null;
        }

        return is_string($data) ? $data : null;
    }

    /**
     * Set the rate limit into the store
     *
     * @throws \Saloon\Exceptions\UnableToCreateDirectoryException
     * @throws \Saloon\Exceptions\UnableToCreateFileException
     */
    public function set(string $key, string $value, int $ttl): bool
    {
        $this->storage->put($key, $value);

        return true;
    }
}
