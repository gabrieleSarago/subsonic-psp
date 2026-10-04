<?php

namespace App\Data\Subsonic;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A single playable track as returned by the Subsonic API.
 */
final readonly class Song
{
    /**
     * @param  string  $suffix  File extension reported by the server, e.g. "mp3" or "flac".
     * @param  int  $size  Size of the source file in bytes.
     * @param  int  $duration  Length in seconds.
     * @param  CarbonInterface|null  $created  When the track was added to the library.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $artist,
        public string $album,
        public ?string $albumId,
        public ?string $coverArt,
        public string $suffix,
        public string $contentType,
        public int $size,
        public int $duration,
        public int $track,
        public int $discNumber,
        public CarbonInterface $created,
    ) {}

    /**
     * Whether the source file can be handed to the PSP untouched.
     */
    public function isMp3(): bool
    {
        return $this->suffix === 'mp3';
    }

    /**
     * A stable identifier for the audio bytes behind this track.
     *
     * Changing the source file of a track must change this value, otherwise a
     * client that already downloaded it keeps playing the stale copy from its
     * own cache.
     */
    public function contentFingerprint(): string
    {
        return substr(hash('sha256', $this->id.'|'.$this->suffix.'|'.$this->size), 0, 12);
    }

    /**
     * Build a song from the payload of a Subsonic response.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            id: (string) $payload['id'],
            title: (string) ($payload['title'] ?? 'Senza titolo'),
            artist: (string) ($payload['artist'] ?? 'Artista sconosciuto'),
            album: (string) ($payload['album'] ?? ''),
            albumId: isset($payload['albumId']) ? (string) $payload['albumId'] : null,
            coverArt: isset($payload['coverArt']) ? (string) $payload['coverArt'] : null,
            suffix: strtolower((string) ($payload['suffix'] ?? '')),
            contentType: (string) ($payload['contentType'] ?? ''),
            size: (int) ($payload['size'] ?? 0),
            duration: (int) ($payload['duration'] ?? 0),
            track: (int) ($payload['track'] ?? 0),
            discNumber: (int) ($payload['discNumber'] ?? 0),
            created: Carbon::parse((string) ($payload['created'] ?? 'now')),
        );
    }
}
