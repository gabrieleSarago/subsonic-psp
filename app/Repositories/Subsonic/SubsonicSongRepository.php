<?php

namespace App\Repositories\Subsonic;

use App\Data\Subsonic\Song;
use App\Repositories\Contracts\SongRepository;
use App\Services\Subsonic\SubsonicClient;
use Illuminate\Support\Facades\Cache;

final class SubsonicSongRepository implements SongRepository
{
    public function __construct(private readonly SubsonicClient $client) {}

    public function find(string $songId): ?Song
    {
        if ($songId === '') {
            return null;
        }

        $key = "navidrome:song:{$songId}";

        $song = Cache::remember($key, config('navidrome.cache.song'), function () use ($songId): ?Song {
            $response = $this->client->getOrNull('getSong', ['id' => $songId]);

            return isset($response['song'])
                ? Song::fromArray($response['song'])
                : null;
        });

        if (! $song instanceof Song) {
            // A miss is dropped straight away rather than kept for the whole
            // lifetime: the enclosure of a feed points at a fixed URL, so a
            // track added to the library later must be reachable at once.
            Cache::forget($key);

            return null;
        }

        return $song;
    }
}
