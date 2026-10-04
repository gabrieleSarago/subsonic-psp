<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the feed responses look like the feeds the PSP expects.
 *
 * Spatie\Feed\Helpers\FeedContentType maps the "rss" format to
 * "application/xml", and the configuration key that is supposed to override it
 * is read as "feed::contentType", which is not a reachable configuration key.
 * The header is therefore rewritten here, together with the caching directives
 * that keep the PSP from holding a stale feed.
 */
class ForcePspFeedHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isFeedResponse($response)) {
            $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');
            $response->headers->set('Cache-Control', 'no-cache');
        }

        return $response;
    }

    private function isFeedResponse(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type');

        return str_starts_with($contentType, 'application/xml')
            || str_starts_with($contentType, 'text/xml')
            || str_contains($contentType, 'rss+xml');
    }
}
