<?php

namespace App\Data\Radio;

use Illuminate\Support\Str;

/**
 * One station of the curated radio list.
 *
 * Only the stream URL is truly required from whoever edits the file. The
 * identifier is derived from the name when missing, and the name, tagline and
 * favicon are passed to the radios:probe command to be discovered from the
 * stream, so nothing else needs to be typed in.
 */
final readonly class Station
{
    public function __construct(
        public string $id,
        public string $streamUrl,
        public string $name = '',
        public ?string $resolvedUrl = null,
        public ?string $tagline = null,
        public ?string $favicon = null,
    ) {}

    /**
     * Build a station from one row of the JSON file.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $streamUrl = trim((string) ($payload['streamUrl'] ?? ''));

        return new self(
            id: trim((string) ($payload['id'] ?? '')) !== ''
                ? trim((string) $payload['id'])
                : self::defaultId($name, $streamUrl),
            streamUrl: $streamUrl,
            name: $name,
            resolvedUrl: isset($payload['resolvedUrl']) ? trim((string) $payload['resolvedUrl']) : null,
            tagline: isset($payload['tagline']) ? trim((string) $payload['tagline']) : null,
            favicon: isset($payload['favicon']) ? trim((string) $payload['favicon']) : null,
        );
    }

    /**
     * The row written back to the JSON file, keeping only filled fields.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $row = ['id' => $this->id];

        if ($this->name !== '') {
            $row['name'] = $this->name;
        }

        $row['streamUrl'] = $this->streamUrl;

        if ($this->resolvedUrl !== null && $this->resolvedUrl !== '') {
            $row['resolvedUrl'] = $this->resolvedUrl;
        }

        if ($this->tagline !== null && $this->tagline !== '') {
            $row['tagline'] = $this->tagline;
        }

        if ($this->favicon !== null && $this->favicon !== '') {
            $row['favicon'] = $this->favicon;
        }

        return $row;
    }

    /**
     * The name shown when the user did not write one: the host of the stream.
     */
    public function label(): string
    {
        return $this->name !== '' ? $this->name : $this->host();
    }

    /**
     * The URL handed to ffmpeg: the redirect and playlist resolution done by
     * the probe, or the original stream URL when there was nothing to resolve.
     */
    public function playableUrl(): string
    {
        return $this->resolvedUrl !== null && $this->resolvedUrl !== ''
            ? $this->resolvedUrl
            : $this->streamUrl;
    }

    /**
     * The host of the stream, used as a fallback name and for the default
     * favicon location.
     */
    public function host(): string
    {
        $host = parse_url($this->streamUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $this->streamUrl;
    }

    private static function defaultId(string $name, string $streamUrl): string
    {
        if ($name !== '') {
            return Str::slug($name);
        }

        $host = parse_url($streamUrl, PHP_URL_HOST);
        $source = is_string($host) && $host !== '' ? $host : $streamUrl;

        return Str::slug($source);
    }
}
