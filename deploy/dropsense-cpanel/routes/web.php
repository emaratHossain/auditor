<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Screenshots go out behind a temporary signed link that expires in an hour, so
 * a leaked URL does not expose a client's page forever.
 */
Route::get('/screenshots/{path}', function (Request $request, string $path) {
    abort_unless($request->hasValidSignature(), 403);
    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path);
})->where('path', '.*')->name('screenshot');

/**
 * Everything else is React. Without this, refreshing the browser on /audits/42
 * serves a Laravel 404 — the classic first bug of any Laravel + React app.
 */
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api).*$');
