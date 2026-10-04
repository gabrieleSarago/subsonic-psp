<?php

namespace App\Data\Subsonic;

use Illuminate\Support\Collection;

/**
 * An album and, when loaded, its tracks.
 */
final readonly class Album
{
    /**
     * @param  Collection<int, Song>  $songs
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $artist,
        public ?string $coverArt,
        public int $year,
        public int $songCount,
        public int $duration,
        public Collection $songs,
    ) {}

    /**
     * Build the album summary returned by getAlbumList2.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function summaryFromArray(array $payload): self
    {
        return new self(
            id: (string) $payload['id'],
            name: (string) ($payload['name'] ?? 'Senza titolo'),
            artist: (string) ($payload['artist'] ?? 'Artista sconosciuto'),
            coverArt: isset($payload['coverArt']) ? (string) $payload['coverArt'] : null,
            year: (int) ($payload['year'] ?? 0),
            songCount: (int) ($payload['songCount'] ?? 0),
            duration: (int) ($payload['duration'] ?? 0),
            songs: collect(),
        );
    }

    /**
     * Build the full album returned by getAlbum, including its tracks.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $songs = collect($payload['song'] ?? [])
            ->map(fn (array $song): Song => Song::fromArray($song))
            ->sortBy([['discNumber', 'asc'], ['track', 'asc'], ['title', 'asc']])
            ->values();

        return new self(
            id: (string) $payload['id'],
            name: (string) ($payload['name'] ?? 'Senza titolo'),
            artist: (string) ($payload['artist'] ?? 'Artista sconosciuto'),
            coverArt: isset($payload['coverArt']) ? (string) $payload['coverArt'] : null,
            year: (int) ($payload['year'] ?? 0),
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
        return $this->artist === ''
            ? $this->name
            : $this->artist.' - '.$this->name;
    }
}
