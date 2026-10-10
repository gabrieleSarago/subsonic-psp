<?php

namespace App\Support;

use App\Data\Subsonic\Song;

/**
 * Builds the URLs that go into the feeds and on the index page.
 *
 * These URLs are read by the PSP from its own network, so they must not depend
 * on the Host header of the request that generated them. The application root
 * is pinned to APP_URL by the ForceAppUrlFromConfig middleware, which makes
 * route() deterministic.
 */
final class PspUrls
{
    /**
     * The audio URL used as the enclosure of a feed item.
     *
     * The fingerprint changes when the source file behind the track changes,
     * so a client holding an older copy fetches the new one instead of
     * replaying a stale download from its own cache.
     */
    public function audio(Song $song): string
    {
        return route('audio.show', [
            'song' => $song->id,
            'v' => $song->contentFingerprint(),
        ]);
    }

    /**
     * The proxy URL of a cover image, or null when there is no artwork.
     *
     * The size is part of the path rather than of the query, so that one image
     * at two sizes stays two distinct files in any cache in between.
     */
    public function cover(?string $coverArtId, int $size): ?string
    {
        if ($coverArtId === null || $coverArtId === '') {
            return null;
        }

        return route('covers.show.sized', ['cover' => $coverArtId, 'size' => $size]);
    }

    /**
     * The feed URL of an album. The first part has a shorter URL.
     */
    public function albumFeed(string $albumId, int $part = 1): string
    {
        return $part <= 1
            ? route('feeds.albums.show', ['album' => $albumId])
            : route('feeds.albums.part', ['album' => $albumId, 'part' => $part]);
    }

    /**
     * The feed URL of a playlist. The first part has a shorter URL.
     */
    public function playlistFeed(string $playlistId, int $part = 1): string
    {
        return $part <= 1
            ? route('feeds.playlists.show', ['playlist' => $playlistId])
            : route('feeds.playlists.part', ['playlist' => $playlistId, 'part' => $part]);
    }

    /**
     * The feed URL of the radio section. The first part has a shorter URL.
     */
    public function radioFeed(int $part = 1): string
    {
        return $part <= 1
            ? route('feeds.radios.show')
            : route('feeds.radios.part', ['part' => $part]);
    }

    /**
     * The live transcode of a station, used as the enclosure of its feed item.
     */
    public function radioStream(string $stationId): string
    {
        return route('radios.stream.show', ['station' => $stationId]);
    }

    /**
     * The proxied favicon of a station.
     *
     * The remote image is never linked directly: the device refuses most TLS
     * certificates, so the RadioIconController fetches the bytes here and only
     * this address is handed out.
     */
    public function radioIcon(string $stationId): string
    {
        return route('radios.icon.show', ['station' => $stationId]);
    }
}
