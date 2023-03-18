<?php

declare(strict_types=1);

namespace Saloon\RateLimiter\Stores;

use Throwable;
use Saloon\Helpers\Storage;
use Saloon\RateLimiter\Contracts\RateLimiterStore;

class FileStore implements RateLimiterStore
{
    /**
     * Storage Driver
     *
     * @var \Saloon\Helpers\Storage
     */
    protected readonly Storage $storage;

    /**
     * Constructor
     *
     * @param string $directory
     * @throws \Saloon\Exceptions\DirectoryNotFoundException
     * @throws \Saloon\Exceptions\UnableToCreateDirectoryException
     */
    public function __construct(readonly protected string $directory)
    {
        $this->storage = new Storage($this->directory, false);
    }

    /**
     * Get an item from storage
     *
     * @param string $key
     * @return string|null
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
     * Store a file
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return bool
     * @throws \Saloon\Exceptions\UnableToCreateDirectoryException
     * @throws \Saloon\Exceptions\UnableToCreateFileException
     */
    public function set(string $key, string $value, int $ttl): bool
    {
        $this->storage->put($key, $value);

        return true;
    }
}
