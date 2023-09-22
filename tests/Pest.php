<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use Orchestra\Testbench\TestCase as LaravelTestCase;

uses(LaravelTestCase::class)->in('./Laravel');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Parse the raw limit
 */
function parseRawLimit(?string $data): ?array
{
    return ! empty($data) ? json_decode($data, true) : null;
}

/**
 * Reset the testing directory
 */
function getTestingDirectory(): string
{
    $path = 'tests/Fixtures/Temp';

    if (! is_dir($path)) {
        if (! mkdir($path) && ! is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        return $path;
    }

    array_map('unlink', array_filter((array) glob($path . '/*')));

    return $path;
}
