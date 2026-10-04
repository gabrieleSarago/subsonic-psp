<?php

namespace App\Data;

use App\Support\PspUrls;

/**
 * One line of the index page: an album or a playlist, with its feed parts.
 */
final readonly class IndexFeedLink
{
    /**
     * @param  string  $kind  Either "album" or "playlist".
     * @param  string  $initial  First letter of the artist, used for the anchors.
     * @param  string  $meta  Track count and length, written in words.
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
        return $this->kind === 'playlist'
            ? $urls->playlistFeed($this->id, $part)
            : $urls->albumFeed($this->id, $part);
    }
}
