<?php

namespace App\Repositories\Subsonic;

use App\Data\Subsonic\Album;
use App\Repositories\Contracts\AlbumRepository;
use App\Services\Subsonic\SubsonicClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class SubsonicAlbumRepository implements AlbumRepository
{
    private const string LIST_CACHE_KEY = 'navidrome:albums:index';

    public function __construct(private readonly SubsonicClient $client) {}

    public function all(): Collection
    {
        $albums = Cache::remember(
            self::LIST_CACHE_KEY,
            config('navidrome.cache.albums'),
            fn (): Collection => $this->fetchAll(),
        );

        if (! $albums instanceof Collection) {
            // The cache holds objects, and a copy written before a change to
            // their shape comes back unreadable. It is dropped and read again
            // rather than reported as a failure.
            Cache::forget(self::LIST_CACHE_KEY);

            return $this->fetchAll();
        }

        return $albums;
    }

    public function find(string $albumId): ?Album
    {
        if ($albumId === '') {
            return null;
        }

        $key = "navidrome:album:{$albumId}";

        $album = Cache::remember($key, config('navidrome.cache.album'), function () use ($albumId): ?Album {
            $response = $this->client->getOrNull('getAlbum', ['id' => $albumId]);

            return isset($response['album'])
                ? Album::fromArray($response['album'])
                : null;
        });

        if (! $album instanceof Album) {
            // A miss is dropped straight away rather than kept for the whole
            // lifetime: an album added to the library a moment later has to be
            // reachable immediately.
            Cache::forget($key);

            return null;
        }

        return $album;
    }

    public function forget(): void
    {
        Cache::forget(self::LIST_CACHE_KEY);
    }

    /**
     * Load every album, following offsets until the server runs out.
     *
     * Navidrome caps the page size at 500, so a larger library needs more than
     * one call.
     *
     * @return Collection<int, Album>
     */
    private function fetchAll(): Collection
    {
        $size = (int) config('navidrome.album_page_size');
        $albums = collect();
        $offset = 0;

        do {
            $response = $this->client->get('getAlbumList2', [
                'type' => 'alphabeticalByName',
                'size' => $size,
                'offset' => $offset,
            ]);

            $page = collect($response['albumList2']['album'] ?? [])
                ->map(fn (array $payload): Album => Album::summaryFromArray($payload));

            $albums = $albums->concat($page);
            $offset += $size;
        } while ($page->count() === $size);

        return $albums->values();
    }
}
