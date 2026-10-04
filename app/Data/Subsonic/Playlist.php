<?php

namespace App\Data\Subsonic;

use Illuminate\Support\Collection;

/**
 * A playlist and, when loaded, its playable entries.
 */
final readonly class Playlist
{
    /**
     * @param  Collection<int, Song>  $songs
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $coverArt,
        public string $comment,
        public int $songCount,
        public int $duration,
        public Collection $songs,
    ) {}

    /**
     * Build the playlist summary returned by getPlaylists.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function summaryFromArray(array $payload): self
    {
        return new self(
            id: (string) $payload['id'],
            name: (string) ($payload['name'] ?? 'Senza titolo'),
            coverArt: isset($payload['coverArt']) ? (string) $payload['coverArt'] : null,
            comment: (string) ($payload['comment'] ?? ''),
            songCount: (int) ($payload['songCount'] ?? 0),
            duration: (int) ($payload['duration'] ?? 0),
            songs: collect(),
        );
    }

    /**
     * Build the full playlist returned by getPlaylist.
     *
     * Entries whose song payload is missing point at files that have been
     * removed from the library, or at podcast episodes; they are dropped here
     * because there is nothing to hand to the PSP.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $songs = collect($payload['entry'] ?? [])
            ->map(fn (array $entry): ?Song => isset($entry['song'])
                ? Song::fromArray($entry['song'])
                : null)
            ->filter()
            ->values();

        return new self(
            id: (string) $payload['id'],
            name: (string) ($payload['name'] ?? 'Senza titolo'),
            coverArt: isset($payload['coverArt']) ? (string) $payload['coverArt'] : null,
            comment: (string) ($payload['comment'] ?? ''),
            songCount: (int) ($payload['songCount'] ?? $songs->count()),
            duration: (int) ($payload['duration'] ?? 0),
            songs: $songs,
        );
    }

    /**
     * The label used on the index page and as the feed channel title.
     */
    public function label(): string
    {
        return $this->name;
    }
}
