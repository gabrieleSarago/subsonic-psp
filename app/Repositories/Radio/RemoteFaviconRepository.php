<?php

namespace App\Repositories\Radio;

use App\Data\Radio\FaviconImage;
use App\Data\Radio\Station;
use App\Repositories\Contracts\FaviconRepository;
use App\Repositories\Contracts\RadioStationRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches the favicon of a station from the URL the probe discovered.
 *
 * The PSP refuses most TLS certificates, so the remote image is never linked
 * directly: the RadioIconController proxies these bytes, and only its URL ends
 * up in the feeds and on the index page.
 */
final class RemoteFaviconRepository implements FaviconRepository
{
    public function __construct(private readonly RadioStationRepository $stations) {}

    public function find(string $stationId): ?FaviconImage
    {
        $station = $this->stations->find($stationId);

        if (! $station instanceof Station || $station->favicon === null || $station->favicon === '') {
            return null;
        }

        $key = "radios:favicon:{$stationId}";

        $image = Cache::remember(
            $key,
            config('radios.cache.favicon'),
            fn (): ?FaviconImage => $this->fetch($station->favicon),
        );

        return $image instanceof FaviconImage ? $image : null;
    }

    private function fetch(string $url): ?FaviconImage
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->withHeaders(['User-Agent' => (string) config('radios.user_agent')])
                ->get($url);
        } catch (Throwable $exception) {
            // A refused connection, a DNS failure and a TLS handshake the
            // client refuses all land here. Each one means "no image", not an
            // outage of this application.
            return null;
        }

        $contentType = (string) $response->header('Content-Type');

        if (! $response->successful() || ! str_starts_with($contentType, 'image/')) {
            return null;
        }

        return new FaviconImage($response->body(), $contentType);
    }
}
