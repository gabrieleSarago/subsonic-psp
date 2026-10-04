<?php

namespace Tests\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A stand-in for the Subsonic API of Navidrome.
 *
 * It answers the endpoints this application calls, and nothing else, so a test
 * fails as soon as the code under test asks the server something new.
 */
final class SubsonicApi
{
    /**
     * The album details this library holds, keyed by identifier.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $albums = [];

    /**
     * The playlist details this library holds, keyed by identifier.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $playlists = [];

    /**
     * Every track reachable through the collections above, keyed by identifier.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $songs = [];

    /**
     * Every query string the application sent, in order.
     *
     * @var array<int, array<string, mixed>>
     */
    public static array $calls = [];

    public static string $audioBody = 'ID3fake-mp3-bytes';

    public static string $audioContentType = 'audio/mpeg';

    public static int $audioStatus = 200;

    /**
     * Set to make the server drop the connection while a track is being opened.
     *
     * The audio is fetched with the client of Guzzle rather than with the one of
     * the framework, so a refused connection arrives as the exception of Guzzle
     * and not as the one of the framework. This is the only way for a test to
     * reproduce the fault that reaches the device on the request that plays the
     * audio, when the metadata it needs is already cached.
     */
    public static bool $audioConnectionLost = false;

    /**
     * @var array<string, string>
     */
    public static array $audioHeaders = ['Accept-Ranges' => 'bytes'];

    /**
     * A one pixel transparent GIF, used as the artwork placeholder.
     */
    public const string COVER_BYTES = "\x47\x49\x46\x89\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    /**
     * Install the fake and load the given library.
     *
     * Install it once per test: a second call to Http::fake() would be appended
     * to the first one instead of replacing it, so tests that change the library
     * halfway through use serve() rather than calling this again.
     *
     * @param  array<int, array<string, mixed>>  $albums  Album details, each carrying a "song" list.
     * @param  array<int, array<string, mixed>>  $playlists  Playlist details, each carrying an "entry" list.
     * @param  array<int, array<string, mixed>>  $songs  Standalone track details.
     */
    public static function fake(array $albums = [], array $playlists = [], array $songs = []): void
    {
        self::resetAnswers();

        self::serve($albums, $playlists, $songs);

        Http::fake(function (Request $request): PromiseInterface {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $endpoint = self::endpointOf($request->url());

            self::$calls[] = ['endpoint' => $endpoint, ...$query];

            return self::answer($request, $endpoint, $query);
        });
    }

    /**
     * Put the answers of the streaming endpoints back to their defaults.
     *
     * They are static so that a test can change them after the fake is
     * installed, which means they would otherwise survive into the next one: a
     * test that made the server answer with an API error would see it again for
     * every request of every test that follows.
     */
    private static function resetAnswers(): void
    {
        self::$audioBody = 'ID3fake-mp3-bytes';
        self::$audioContentType = 'audio/mpeg';
        self::$audioStatus = 200;
        self::$audioHeaders = ['Accept-Ranges' => 'bytes'];
        self::$audioConnectionLost = false;
    }

    /**
     * Replace the library the fake answers from, without touching the fake.
     *
     * @param  array<int, array<string, mixed>>  $albums
     * @param  array<int, array<string, mixed>>  $playlists
     * @param  array<int, array<string, mixed>>  $songs
     */
    public static function serve(array $albums = [], array $playlists = [], array $songs = []): void
    {
        self::$albums = self::indexById($albums);
        self::$playlists = self::indexById($playlists);
        self::$songs = self::reachableSongs($albums, $playlists, $songs);
        self::$calls = [];
    }

    /**
     * The queries sent to one endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function callsTo(string $endpoint): array
    {
        return array_values(array_filter(
            self::$calls,
            fn (array $call): bool => $call['endpoint'] === $endpoint,
        ));
    }

    /**
     * Build an album detail payload with the given tracks.
     *
     * @param  array<int, array<string, mixed>>  $songs
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function album(array $songs = [], array $overrides = []): array
    {
        return array_merge([
            'id' => 'al-1',
            'name' => 'Album di prova',
            'artist' => 'Artista di prova',
            'artistId' => 'ar-1',
            'coverArt' => 'al-1',
            'year' => 2001,
            'songCount' => count($songs),
            'duration' => self::totalDuration($songs),
            'song' => array_values($songs),
        ], $overrides);
    }

    /**
     * Build a playlist payload with the given entries.
     *
     * @param  array<int, array<string, mixed>>  $songs
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function playlist(array $songs = [], array $overrides = []): array
    {
        return array_merge([
            'id' => 'pl-1',
            'name' => 'Playlist di prova',
            'coverArt' => null,
            'comment' => '',
            'songCount' => count($songs),
            'duration' => self::totalDuration($songs),
            'entry' => array_map(
                fn (array $song): array => ['song' => $song],
                array_values($songs),
            ),
        ], $overrides);
    }

    /**
     * Build a track payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function song(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sg-1',
            'title' => 'Brano di prova',
            'album' => 'Album di prova',
            'artist' => 'Artista di prova',
            'albumId' => 'al-1',
            'coverArt' => 'al-1',
            'suffix' => 'mp3',
            'contentType' => 'audio/mpeg',
            'size' => 1000000,
            'duration' => 60,
            'track' => 1,
            'discNumber' => 1,
            'created' => '2026-01-15T10:00:00Z',
        ], $overrides);
    }

    /**
     * A run of distinct tracks, useful to exercise the split of long feeds.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<int, array<string, mixed>>
     */
    public static function songs(int $count, array $overrides = []): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): array => self::song([
                'id' => 'sg-'.$index,
                'title' => 'Brano '.$index,
                'track' => $index,
                ...$overrides,
            ]))
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array<string, array<string, mixed>>
     */
    private static function indexById(array $payloads): array
    {
        $indexed = [];

        foreach ($payloads as $payload) {
            $indexed[(string) $payload['id']] = $payload;
        }

        return $indexed;
    }

    /**
     * Every track of the library, so that getSong answers for tracks that only
     * appear inside a collection.
     *
     * @param  array<int, array<string, mixed>>  ...$groups
     * @return array<string, array<string, mixed>>
     */
    private static function reachableSongs(array ...$groups): array
    {
        $songs = self::indexById($groups[2] ?? []);

        foreach ($groups as $group) {
            foreach ($group as $payload) {
                foreach (($payload['song'] ?? []) as $song) {
                    $songs[(string) $song['id']] = $song;
                }

                foreach (($payload['entry'] ?? []) as $entry) {
                    if (isset($entry['song'])) {
                        $songs[(string) $entry['song']['id']] = $entry['song'];
                    }
                }
            }
        }

        return $songs;
    }

    private static function endpointOf(string $url): string
    {
        return basename((string) parse_url($url, PHP_URL_PATH), '.view');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private static function answer(Request $request, string $endpoint, array $query): PromiseInterface
    {
        return match ($endpoint) {
            'ping' => self::ok([
                'status' => 'ok',
                'version' => '1.16.1',
                'type' => 'navidrome',
                'serverVersion' => '0.53.3',
            ]),
            'getAlbumList2' => self::albumList($query),
            'getAlbum' => self::one(self::$albums, (string) ($query['id'] ?? ''), 'album'),
            'getPlaylists' => self::ok([
                'status' => 'ok',
                'playlists' => ['playlist' => array_map(self::summary(...), array_values(self::$playlists))],
            ]),
            'getPlaylist' => self::one(self::$playlists, (string) ($query['id'] ?? ''), 'playlist'),
            'getSong' => self::one(self::$songs, (string) ($query['id'] ?? ''), 'song'),
            'getCoverArt' => Http::response(self::COVER_BYTES, 200, ['Content-Type' => 'image/gif']),
            'stream' => self::$audioConnectionLost
                ? self::connectionLost($request->url())
                : Http::response(
                    self::$audioBody,
                    self::$audioStatus,
                    self::$audioHeaders + ['Content-Type' => self::$audioContentType],
                ),
            default => self::failure(0, 'Endpoint sconosciuto: '.$endpoint),
        };
    }

    /**
     * Refuse the connection the way a server that is not there refuses it.
     */
    private static function connectionLost(string $url): PromiseInterface
    {
        $message = "cURL error 7: Failed to connect to {$url} port 80: Connection refused (see https://curl.se/libcurl/c/libcurl-errors.html)";

        return Create::rejectionFor(new ConnectException($message, new PsrRequest('GET', $url)));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private static function albumList(array $query): PromiseInterface
    {
        $size = (int) ($query['size'] ?? 10);
        $offset = (int) ($query['offset'] ?? 0);

        return self::ok([
            'status' => 'ok',
            'albumList2' => [
                'album' => array_map(
                    self::summary(...),
                    array_slice(array_values(self::$albums), $offset, $size),
                ),
            ],
        ]);
    }

    /**
     * The fields an entry carries in a list, where its tracks are left out.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function summary(array $payload): array
    {
        return array_diff_key($payload, ['song' => null, 'entry' => null]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $source
     */
    private static function one(array $source, string $id, string $key): PromiseInterface
    {
        if ($id === '' || ! isset($source[$id])) {
            // 70 is the Subsonic code for "the requested data was not found".
            return self::failure(70, 'Not found: '.$id);
        }

        return self::ok(['status' => 'ok', $key => $source[$id]]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function ok(array $payload): PromiseInterface
    {
        return Http::response(['subsonic-response' => $payload]);
    }

    private static function failure(int $code, string $message): PromiseInterface
    {
        // Navidrome reports API level problems with an HTTP 200 status, so the
        // payload has to be inspected to notice them.
        return Http::response([
            'subsonic-response' => [
                'status' => 'failed',
                'version' => '1.16.1',
                'error' => ['code' => $code, 'message' => $message],
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $songs
     */
    private static function totalDuration(array $songs): int
    {
        return array_sum(array_map(
            fn (array $song): int => (int) ($song['duration'] ?? 0),
            $songs,
        ));
    }
}
