<?php

declare(strict_types=1);

test('you can save and update a limiter from the store', function () {
    //
});

test('if the stored limit does not contain the timestamp or hits then it will throw an exception while updating', function () {
    //
});

test('if the current timestamp is after the expiry then the limit will not update', function () {
    //
});

test('the rate limiter store instance is kept open on the connector', function () {
    //
});
