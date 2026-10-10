<?php

namespace App\Data;

use App\Support\PspUrls;

/**
 * One line of the index page: an album, a playlist or a radio station, with
 * its feed parts.
 */
final readonly class IndexFeedLink
{
    /**
     * @param  string  $kind  "album", "playlist" or "radio".
     * @param  string  $initial  First letter of the artist, used for the anchors.
     * @param  string  $meta  Track count and length, or the bitrate for a station, written in words.
     * @param  string|null  $coverArt  Cover identifier, turned into a URL by PspUrls.
     * @param  int  $parts  How many feeds this entry is split into.
     */
    public function __construct(
        public string $kind,
        public string $id,
        public string $title,
        public string $subtitle,
        public string $initial,
        public string $meta,
        public ?string $coverArt,
        public int $parts,
    ) {}

    /**
     * The URL of one part of the feed of this entry.
     */
    public function feedUrl(PspUrls $urls, int $part = 1): string
    {
        return match ($this->kind) {
            'playlist' => $urls->playlistFeed($this->id, $part),
            'radio' => $urls->radioFeed($part),
            default => $urls->albumFeed($this->id, $part),
        };
    }
}
