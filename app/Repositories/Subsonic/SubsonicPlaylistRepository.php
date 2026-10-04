<?php

namespace App\Repositories\Subsonic;

use App\Data\Subsonic\Playlist;
use App\Repositories\Contracts\PlaylistRepository;
use App\Services\Subsonic\SubsonicClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class SubsonicPlaylistRepository implements PlaylistRepository
{
    private const string LIST_CACHE_KEY = 'navidrome:playlists:index';

    public function __construct(private readonly SubsonicClient $client) {}

    public function all(): Collection
    {
        $playlists = Cache::remember(
            self::LIST_CACHE_KEY,
            config('navidrome.cache.playlists'),
            fn (): Collection => $this->fetchAll(),
        );

        if (! $playlists instanceof Collection) {
            // The cache holds objects, and a copy written before a change to
            // their shape comes back unreadable. It is dropped and read again
            // rather than reported as a failure.
            Cache::forget(self::LIST_CACHE_KEY);

            return $this->fetchAll();
        }

        return $playlists;
    }

    public function find(string $playlistId): ?Playlist
    {
        if ($playlistId === '') {
            return null;
        }

        $key = "navidrome:playlist:{$playlistId}";

        $playlist = Cache::remember($key, config('navidrome.cache.playlist'), function () use ($playlistId): ?Playlist {
            $response = $this->client->getOrNull('getPlaylist', ['id' => $playlistId]);

            return isset($response['playlist'])
                ? Playlist::fromArray($response['playlist'])
                : null;
        });

        if (! $playlist instanceof Playlist) {
            // A miss is dropped straight away rather than kept for the whole
            // lifetime: a playlist created a moment later has to be reachable
            // immediately.
            Cache::forget($key);

            return null;
        }

        return $playlist;
    }

    public function forget(): void
    {
        Cache::forget(self::LIST_CACHE_KEY);
    }

    /**
     * @return Collection<int, Playlist>
     */
    private function fetchAll(): Collection
    {
        $response = $this->client->get('getPlaylists');

        return collect($response['playlists']['playlist'] ?? [])
            ->map(fn (array $payload): Playlist => Playlist::summaryFromArray($payload))
            ->sortBy(fn (Playlist $playlist): string => $playlist->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
