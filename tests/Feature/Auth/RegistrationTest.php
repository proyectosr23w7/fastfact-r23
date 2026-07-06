<?php

use Illuminate\Support\Facades\Route;

test('public registration routes are disabled', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});
