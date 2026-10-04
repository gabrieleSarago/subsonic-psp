<?php

namespace App\Http\Controllers;

use App\Data\Subsonic\Playlist;
use App\Http\Requests\PlaylistFeedRequest;
use App\Repositories\Contracts\PlaylistRepository;
use App\Services\PspFeedService;
use Spatie\Feed\Feed;

class PlaylistFeedController extends Controller
{
    public function __construct(
        private readonly PlaylistRepository $playlists,
        private readonly PspFeedService $feeds,
    ) {}

    /**
     * Serve one part of the feed of a playlist.
     *
     * The first part is reachable under a short URL and the following ones
     * under an explicit part number. Both routes land here: the part comes from
     * the request, and defaults to the first one.
     */
    public function show(PlaylistFeedRequest $request): Feed
    {
        $playlist = $this->playlists->find($request->playlistId());

        if (! $playlist instanceof Playlist) {
            abort(404, 'Playlist non trovata.');
        }

        return $this->feeds->forPlaylist($playlist, $request->part());
    }
}
