<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins generated URLs to APP_URL.
 *
 * The feeds carry absolute URLs that the PSP fetches from its own network. If
 * they were built from the incoming Host header they would follow whatever name
 * the client happened to use, which breaks as soon as the PSP reaches the
 * application by IP address, or through a different name than APP_URL.
 */
class ForceAppUrlFromConfig
{
    public function handle(Request $request, Closure $next): Response
    {
        URL::forceRootUrl(rtrim((string) config('app.url'), '/'));

        return $next($request);
    }
}
