<?php

namespace App\Services;

use App\Data\Subsonic\Album;
use App\Data\Subsonic\Playlist;
use App\Data\Subsonic\Song;
use App\Services\Feeds\SongFeedItemFactory;
use App\Support\PspUrls;
use Illuminate\Support\Collection;
use Spatie\Feed\Feed;

/**
 * Builds the RSS feeds consumed by the PSP.
 *
 * The PSP stops reading a feed after roughly one hundred items, so an album or
 * playlist longer than that is served as a sequence of parts. Part one is
 * reachable under a short URL, the following ones under an explicit part
 * number.
 */
final class PspFeedService
{
    private const int COVER_SIZE = 240;

    /**
     * Maximum number of items in a single feed.
     */
    private readonly int $pageSize;

    public function __construct(
        private readonly SongFeedItemFactory $items,
        private readonly PspUrls $urls,
        int $pageSize,
    ) {
        $this->pageSize = max(1, $pageSize);
    }

    /**
     * Build the feed for one part of an album.
     */
    public function forAlbum(Album $album, int $part = 1): Feed
    {
        $songs = $album->songs;
        $totalParts = $this->partCount($songs->count());
        [$page, $part] = $this->resolvePage($songs, $part, $totalParts);

        return $this->build(
            title: $album->label().($totalParts > 1 ? " (parte {$part} di {$totalParts})" : ''),
            description: $this->describeRange($songs->count(), $part, $page->count()),
            image: $album->coverArt,
            songs: $page,
        );
    }

    /**
     * Build the feed for one part of a playlist.
     */
    public function forPlaylist(Playlist $playlist, int $part = 1): Feed
    {
        $songs = $playlist->songs;
        $totalParts = $this->partCount($songs->count());
        [$page, $part] = $this->resolvePage($songs, $part, $totalParts);

        return $this->build(
            title: $playlist->label().($totalParts > 1 ? " (parte {$part} di {$totalParts})" : ''),
            description: $this->describeRange($songs->count(), $part, $page->count()),
            image: $playlist->coverArt,
            songs: $page,
        );
    }

    /**
     * How many parts a feed of the given length is split into.
     */
    public function partCount(int $songCount): int
    {
        return (int) max(1, ceil($songCount / $this->pageSize));
    }

    /**
     * How many items the given part holds.
     */
    public function partSize(int $songCount, int $part): int
    {
        $remaining = max(0, $songCount - (($part - 1) * $this->pageSize));

        return min($this->pageSize, $remaining);
    }

    /**
     * Pick the requested slice, rejecting parts that do not exist.
     *
     * @param  Collection<int, Song>  $songs
     * @return array{0: Collection<int, Song>, 1: int}
     */
    private function resolvePage(Collection $songs, int $part, int $totalParts): array
    {
        $part = max(1, $part);

        if ($part > $totalParts) {
            abort(404, 'Questa parte del feed non esiste.');
        }

        return [$songs->forPage($part, $this->pageSize)->values(), $part];
    }

    /**
     * @param  Collection<int, Song>  $songs
     */
    private function build(string $title, string $description, ?string $image, Collection $songs): Feed
    {
        return new Feed(
            title: $title,
            items: $songs->map(fn (Song $song) => $this->items->make($song)),
            url: request()->url(),
            view: 'feeds.rss',
            description: $description,
            language: 'it',
            image: $this->urls->cover($image, self::COVER_SIZE) ?? '',
            format: 'rss',
        );
    }

    private function describeRange(int $totalSongs, int $part, int $pageSize): string
    {
        if ($totalSongs === 0) {
            return 'Nessun brano disponibile';
        }

        $from = (($part - 1) * $this->pageSize) + 1;
        $to = $from + $pageSize - 1;

        return $to >= $totalSongs
            ? sprintf('tracce %d-%d di %d', $from, $totalSongs, $totalSongs)
            : sprintf('tracce %d-%d di %d', $from, $to, $totalSongs);
    }
}
