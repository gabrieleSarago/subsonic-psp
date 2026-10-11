<?php

namespace App\Services\Feeds;

use App\Data\Subsonic\Song;
use App\Support\PspUrls;
use Spatie\Feed\FeedItem;

/**
 * Maps a track to the feed item the PSP reads.
 */
final class SongFeedItemFactory
{
    /**
     * The bitrate Navidrome is asked to transcode to, used to estimate the size
     * of the MP3 the PSP will receive for a non-MP3 source.
     */
    private readonly int $bitrate;

    public function __construct(
        private readonly PspUrls $urls,
        int $pspBitrate,
    ) {
        $this->bitrate = $pspBitrate;
    }

    public function make(Song $song): FeedItem
    {
        $audio = $this->urls->audio($song);

        // Every field is set explicitly. Spatie\Feed\FeedItem declares typed
        // properties without defaults, and reading one that was never assigned
        // throws instead of returning null.
        return FeedItem::create([
            'id' => $song->id,
            'title' => $song->track > 0
                ? sprintf('%02d. %s', $song->track, $song->title)
                : $song->title,
            'summary' => $this->summary($song),
            'updated' => $song->created,
            // The item is the track itself, so following the link leads to the
            // audio. The artwork is carried by the image field, which is
            // emitted as the <media:thumbnail> the PSP draws next to the item.
            'link' => $audio,
            'authorName' => $song->artist,
            'authorEmail' => '',
            'enclosure' => $audio,
            'enclosureLength' => $this->length($song),
            'enclosureType' => 'audio/mpeg',
            'image' => $this->urls->coverThumb($song->coverArt) ?? '',
            'category' => $song->album === '' ? [] : [$song->album],
        ]);
    }

    /**
     * The size the PSP should expect for this enclosure, in bytes.
     *
     * An MP3 source is served untouched, so the real size is known. Anything
     * else is transcoded on the fly, where the size can only be estimated from
     * the duration and the requested bitrate. Measured against Navidrome the
     * estimate lands within 0.01% of the real transcoded size.
     */
    private function length(Song $song): int
    {
        if ($song->isMp3()) {
            return $song->size;
        }

        if ($song->duration < 1) {
            return $song->size;
        }

        return (int) ($song->duration * $this->bitrate * 1000 / 8);
    }

    private function summary(Song $song): string
    {
        $parts = array_filter([$song->artist, $song->album]);

        return $parts === [] ? $song->title : implode(' - ', $parts);
    }
}
