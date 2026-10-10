<?php

namespace App\Services\Feeds;

use App\Data\Radio\Station;
use App\Support\PspUrls;
use Spatie\Feed\FeedItem;

/**
 * Maps a station to the feed item the PSP reads.
 */
final class RadioFeedItemFactory
{
    public function __construct(private readonly PspUrls $urls) {}

    public function make(Station $station): FeedItem
    {
        $stream = $this->urls->radioStream($station->id);

        // Every field is set explicitly. Spatie\Feed\FeedItem declares typed
        // properties without defaults, and reading one that was never assigned
        // throws instead of returning null.
        return FeedItem::create([
            'id' => 'radio:'.$station->id,
            'title' => $station->label(),
            'summary' => $station->tagline ?? '',
            'updated' => now(),
            // A station is the stream itself, so following the link leads to
            // the audio, like for a track.
            'link' => $stream,
            'authorName' => 'Radio',
            'authorEmail' => '',
            'enclosure' => $stream,
            // A live stream has no size: the device is told the only true
            // thing there is, that the enclosure is of unknown length.
            'enclosureLength' => 0,
            'enclosureType' => 'audio/mpeg',
            'image' => $this->urls->radioIcon($station->id),
            'category' => [],
        ]);
    }
}
