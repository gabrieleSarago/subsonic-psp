<?php

namespace App\Http\Controllers;

use App\Http\Requests\RadioFeedRequest;
use App\Services\Radio\RadioFeedService;
use Spatie\Feed\Feed;

class RadioFeedController extends Controller
{
    public function __construct(private readonly RadioFeedService $feeds) {}

    /**
     * Serve one part of the radio feed.
     *
     * The first part is reachable under a short URL and the following ones
     * under an explicit part number. Both routes land here: the part comes
     * from the request, and defaults to the first one.
     */
    public function show(RadioFeedRequest $request): Feed
    {
        return $this->feeds->feed($request->part());
    }
}
