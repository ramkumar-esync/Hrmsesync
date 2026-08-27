<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * The API lives in routes/api.php. This file serves the built Vue single-page
 * app for every other path, so the whole thing runs from one origin — no CORS,
 * no second vhost.
 *
 * The SPA's index.html is copied to resources/spa/index.html at deploy time
 * (see the deploy steps in DEPLOYMENT.md). Its hashed JS/CSS live under the web
 * root at /assets and are served directly by the web server before a request
 * ever reaches PHP; only the HTML shell comes through here.
 *
 * The negative lookahead keeps this from swallowing /api, the /up health check,
 * or /storage. Vue Router (history mode) handles the path on the client, so a
 * deep link like /leave/apply returns the same shell and the router takes over.
 */
Route::get('/{any}', function () {
    $shell = base_path('resources/spa/index.html');

    abort_unless(is_file($shell), 503, 'The application has not been built yet.');

    return response(file_get_contents($shell))
        ->header('Content-Type', 'text/html; charset=UTF-8');
})->where('any', '^(?!api|up|storage).*$');
