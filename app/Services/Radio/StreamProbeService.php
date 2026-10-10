<?php

namespace App\Services\Radio;

use App\Data\Radio\ProbeResponse;
use App\Data\Radio\StationProbe;
use App\Services\Radio\Contracts\StreamProbeFetcher;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Throwable;

/**
 * Discovers what a station is by interrogating its stream.
 *
 * A station can be a direct audio stream, a Winamp .pls, an .m3u or an HLS
 * playlist. Playlists are followed (with a depth limit and a visited list) to
 * the audio stream itself, which is what the transcoder prefers. The metadata
 * comes from the icy-* headers of the stream, or from the radio-browser
 * directory as a fallback; the favicon from the station homepage, when the
 * header names one, and then from the directory.
 */
final class StreamProbeService
{
    private const string RADIO_BROWSER_SEARCH = 'https://de1.api.radio-browser.info/json/stations/search?limit=1&hidebroken=true&order=clickcount&reverse=true';

    public function __construct(
        private readonly StreamProbeFetcher $fetcher,
        private readonly int $maxFollows,
        private readonly int $probeBytes,
    ) {}

    /**
     * Probe a station and summarize what was learned.
     *
     * @param  string|null  $nameHint  The curated name, used to search the
     *                                 directory for the favicon when the stream
     *                                 names no homepage. Never used to override
     *                                 a name the user wrote.
     */
    public function probe(string $streamUrl, ?string $nameHint = null): ?StationProbe
    {
        $response = $this->follow($streamUrl, $this->maxFollows, []);

        if ($response === null) {
            return null;
        }

        $searchName = $nameHint !== null && trim($nameHint) !== ''
            ? trim($nameHint)
            : trim($response->icy['name'] ?? '');

        $homepage = trim($response->icy['url'] ?? '');
        $favicon = null;
        $radioBrowser = null;

        if ($homepage !== '' && preg_match('#^https?://#i', $homepage)) {
            $favicon = $this->iconFromHomepage($homepage) ?? $this->iconFallback($homepage);
        }

        if ($favicon === null || $this->tagline($response) === null) {
            $radioBrowser = $this->radioBrowserSearch($searchName);

            if ($radioBrowser !== null) {
                $favicon ??= $this->stringOrNull($radioBrowser['favicon'] ?? '');
            }
        }

        $tagline = $this->tagline($response);

        if ($tagline === null && $radioBrowser !== null) {
            $tagline = $this->stringOrNull($radioBrowser['tags'] ?? '');
        }

        return new StationProbe(
            resolvedUrl: $response->url,
            name: $this->stringOrNull($response->icy['name'] ?? ''),
            tagline: $tagline,
            favicon: $favicon,
        );
    }

    /**
     * Follow a stream and, when it is a playlist, the stream it points at.
     */
    private function follow(string $url, int $followsLeft, array $visited): ?ProbeResponse
    {
        $response = $this->fetcher->fetchStream($url, $this->probeBytes);

        if ($response === null || $response->status >= 400) {
            return null;
        }

        $visited[] = $url;

        if ($followsLeft > 0 && $this->isFollowablePlaylist($response)) {
            $next = $this->playlistTarget($response);

            if ($next !== null && ! in_array($next, $visited, true)) {
                return $this->follow($next, $followsLeft - 1, $visited);
            }
        }

        return $response;
    }

    private function isFollowablePlaylist(ProbeResponse $response): bool
    {
        if ($this->isHls($response)) {
            // ffmpeg reads an HLS playlist as it is, and following it would
            // descend into a segment, which is not a stream.
            return false;
        }

        $type = $response->contentType;
        $body = ltrim($response->body);

        return str_contains($type, 'scpls')
            || str_contains($type, 'mpegurl')
            || str_starts_with($body, '[playlist]')
            || str_starts_with($body, '#EXTM3U');
    }

    /**
     * Whether a playlist is HLS rather than a plain list of streams.
     *
     * The content type alone is not enough: a Winamp .m3u is served as
     * "audio/x-mpegurl" too. The HLS-specific "EXT-X-" tags, and the ".m3u8"
     * extension, are what tell the two apart.
     */
    private function isHls(ProbeResponse $response): bool
    {
        if (str_contains($response->contentType, 'apple.mpegurl')) {
            return true;
        }

        if (str_contains($response->body, '#EXT-X-')) {
            return true;
        }

        $path = (string) parse_url($response->url, PHP_URL_PATH);

        return str_ends_with(strtolower($path), '.m3u8');
    }

    /**
     * The first playable URL named by a playlist.
     */
    private function playlistTarget(ProbeResponse $response): ?string
    {
        $body = trim($response->body);

        if (str_contains($response->contentType, 'scpls') || str_starts_with($body, '[playlist]')) {
            if (preg_match('/^\s*File\d+\s*=\s*(\S+)\s*$/mi', $body, $match)) {
                return $this->resolveUrl($response->url, trim($match[1]));
            }

            return null;
        }

        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }

            if (! str_contains($line, '://') && ! str_starts_with($line, '/')) {
                continue;
            }

            return $this->resolveUrl($response->url, $line);
        }

        return null;
    }

    /**
     * The tagline of a station.
     *
     * Some servers fill icy-description with the mount name rather than with a
     * sentence: it is refused when it equals the icy-name or looks like a
     * path, falling back to the genre.
     */
    private function tagline(ProbeResponse $response): ?string
    {
        $name = trim($response->icy['name'] ?? '');
        $description = trim($response->icy['description'] ?? '');
        $genre = trim($response->icy['genre'] ?? '');

        if ($description !== '' && $description !== $name && ! str_contains($description, '/')) {
            return $description;
        }

        return $genre === '' ? null : $genre;
    }

    private function iconFromHomepage(string $homepage): ?string
    {
        $html = $this->fetcher->fetchText($homepage, 262144);

        if ($html === null || ! preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            return null;
        }

        foreach ($tags[0] as $tag) {
            if (! preg_match('/\brel\s*=\s*["\']?[^"\'>\s]*icon/i', $tag)) {
                continue;
            }

            if (preg_match('/\bhref\s*=\s*["\']([^"\']+)["\']/i', $tag, $match)) {
                return $this->resolveUrl($homepage, trim($match[1]));
            }
        }

        return null;
    }

    /**
     * The conventional location of a favicon, used when the homepage names none.
     */
    private function iconFallback(string $homepage): ?string
    {
        $parts = parse_url($homepage);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $base = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $base .= ':'.$parts['port'];
        }

        return $base.'/favicon.ico';
    }

    /**
     * The directory record of the station with the given name, if any.
     *
     * @return array<string, mixed>|null
     */
    private function radioBrowserSearch(string $name): ?array
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $payload = $this->fetcher->fetchText(
            self::RADIO_BROWSER_SEARCH.'&name='.rawurlencode($name),
            65536,
        );

        if ($payload === null) {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        return is_array($decoded[0]) ? $decoded[0] : null;
    }

    private function resolveUrl(string $base, string $relative): string
    {
        try {
            return (string) UriResolver::resolve(new Uri($base), new Uri($relative));
        } catch (Throwable) {
            return $relative;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
