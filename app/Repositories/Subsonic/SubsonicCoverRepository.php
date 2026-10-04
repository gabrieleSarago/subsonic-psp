<?php

namespace App\Repositories\Subsonic;

use App\Data\Subsonic\CoverImage;
use App\Repositories\Contracts\CoverRepository;
use App\Services\Subsonic\SubsonicClient;
use Illuminate\Support\Facades\Cache;

final class SubsonicCoverRepository implements CoverRepository
{
    public function __construct(private readonly SubsonicClient $client) {}

    public function find(string $coverArtId, int $size): ?CoverImage
    {
        if ($coverArtId === '' || $size < 1) {
            return null;
        }

        $key = "navidrome:cover:{$coverArtId}:{$size}";

        $image = Cache::remember($key, config('navidrome.cache.cover'), function () use ($coverArtId, $size): ?CoverImage {
            $response = $this->client->getBinary('getCoverArt', ['id' => $coverArtId, 'size' => $size]);

            $contentType = (string) $response->header('Content-Type');

            // A failed API call answers with XML or JSON and an HTTP 200, so the
            // content type is the only reliable signal that this is an image.
            if (! $response->successful() || ! str_starts_with($contentType, 'image/')) {
                return null;
            }

            return new CoverImage($response->body(), $contentType);
        });

        return $image instanceof CoverImage ? $image : null;
    }
}
