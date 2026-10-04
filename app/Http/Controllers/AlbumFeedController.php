<?php

namespace App\Http\Controllers;

use App\Data\Subsonic\Album;
use App\Http\Requests\AlbumFeedRequest;
use App\Repositories\Contracts\AlbumRepository;
use App\Services\PspFeedService;
use Spatie\Feed\Feed;

class AlbumFeedController extends Controller
{
    public function __construct(
        private readonly AlbumRepository $albums,
        private readonly PspFeedService $feeds,
    ) {}

    /**
     * Serve one part of the feed of an album.
     *
     * The first part is reachable under a short URL and the following ones
     * under an explicit part number. Both routes land here: the part comes from
     * the request, and defaults to the first one.
     */
    public function show(AlbumFeedRequest $request): Feed
    {
        $album = $this->albums->find($request->albumId());

        if (! $album instanceof Album) {
            abort(404, 'Album non trovato.');
        }

        return $this->feeds->forAlbum($album, $request->part());
    }
}
