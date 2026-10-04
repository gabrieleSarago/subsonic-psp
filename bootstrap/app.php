<?php

use App\Exceptions\AudioProxyException;
use App\Http\Middleware\ForceAppUrlFromConfig;
use App\Services\Subsonic\SubsonicException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applied to every response: the URLs inside the feeds must not follow
        // whatever Host header the request happened to carry.
        $middleware->append(ForceAppUrlFromConfig::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The music server is a separate machine. Saying so plainly is more
        // useful to the device than a generic error page, but only up to a
        // point: what the client reports about a failed connection is the
        // signed URL it was handed, so the exception decides what the device
        // is allowed to read.
        $exceptions->render(function (SubsonicException $exception): Response {
            report($exception);

            // Plain text on every endpoint, including the audio proxy: a device
            // that is playing has no use for an HTML page, and the audio one is
            // where an outage shows up first.
            return response($exception->deviceMessage(), 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        });

        $exceptions->render(function (AudioProxyException $exception): Response {
            report($exception);

            return response($exception->getMessage(), $exception->status, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        });
    })->create();
