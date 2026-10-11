<?php

namespace App\Http\Controllers;

use App\Data\IndexFeedLink;
use App\Data\Subsonic\Album;
use App\Data\Subsonic\Playlist;
use App\Repositories\Contracts\AlbumRepository;
use App\Repositories\Contracts\PlaylistRepository;
use App\Repositories\Contracts\RadioStationRepository;
use App\Services\PspFeedService;
use App\Support\Duration;
use App\Support\PspUrls;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FeedIndexController extends Controller
{
    public function __construct(
        private readonly AlbumRepository $albums,
        private readonly PlaylistRepository $playlists,
        private readonly RadioStationRepository $radios,
        private readonly PspFeedService $feeds,
        private readonly PspUrls $urls,
    ) {}

    /**
     * The page the PSP browser opens: the radio feed up top, then every album
     * and playlist with a link to each of its feed parts.
     *
     * Nothing here is hardcoded. The lists are read from Navidrome on every
     * request, so a playlist created on the server shows up without a restart.
     */
    public function __invoke(): View
    {
        $playlists = $this->playlistLinks();
        $albums = $this->albumLinks();
        $radioCount = $this->radios->all()->count();

        return view('psp.index', [
            'playlists' => $playlists,
            'albums' => $albums,
            'initials' => $albums->pluck('initial')->unique()->sort()->values(),
            'urls' => $this->urls,
            'playlistCount' => $playlists->count(),
            'albumCount' => $albums->count(),
            'radioCount' => $radioCount,
            // Debug scaffolding for the item icon question, removed with the
            // probe feed once it is settled.
            'testFeedUrl' => route('psp-test.feed'),
        ]);
    }

    /**
     * @return Collection<int, IndexFeedLink>
     */
    private function playlistLinks(): Collection
    {
        return $this->playlists->all()
            ->map(fn (Playlist $playlist): IndexFeedLink => new IndexFeedLink(
                kind: 'playlist',
                id: $playlist->id,
                title: $playlist->name,
                subtitle: 'Playlist',
                initial: $this->initial($playlist->name),
                meta: $this->meta($playlist->songCount, $playlist->duration),
                coverArt: null,
                parts: $this->feeds->partCount($playlist->songCount),
            ))
            ->values();
    }

    /**
     * @return Collection<int, IndexFeedLink>
     */
    private function albumLinks(): Collection
    {
        return $this->albums->all()
            ->map(fn (Album $album): IndexFeedLink => new IndexFeedLink(
                kind: 'album',
                id: $album->id,
                title: $album->name,
                subtitle: $album->artist,
                initial: $this->initial($album->artist),
                meta: $this->meta($album->songCount, $album->duration),
                coverArt: $album->coverArt,
                parts: $this->feeds->partCount($album->songCount),
            ))
            ->sortBy(
                [['initial', 'asc'], ['subtitle', 'asc'], ['title', 'asc']],
                SORT_NATURAL | SORT_FLAG_CASE,
            )
            ->values();
    }

    private function meta(int $songCount, int $duration): string
    {
        $songs = $songCount === 1 ? '1 brano' : sprintf('%d brani', $songCount);

        return $songs.' - '.Duration::human($duration);
    }

    /**
     * The letter an entry is filed under.
     *
     * The name is trimmed before the first letter is taken, because a leading
     * space would otherwise file an artist under a blank initial, and every one
     * of their albums under the same heading.
     */
    private function initial(string $name): string
    {
        $initial = Str::upper(Str::substr(Str::trim($name), 0, 1));

        return $initial === '' ? '#' : $initial;
    }
}
