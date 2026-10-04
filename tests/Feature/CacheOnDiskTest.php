<?php

use App\Data\Subsonic\Song;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Support\SubsonicApi;

/*
|--------------------------------------------------------------------------
| The cache as it is really used
|--------------------------------------------------------------------------
| The rest of the suite runs on an in-memory cache, which is enough for
| everything except the one thing this file is about: whether what the
| application puts in the cache is what it gets back.
|
| That only shows up on a store that keeps its entries as bytes on disk, which
| is the one a container is deployed with, and on a value that is an object
| rather than a scalar. A store is free to rebuild objects from those bytes
| only for the classes it is told about, and Laravel's own default tells it to
| rebuild none of them: every object then comes back as an incomplete one and
| is quietly discarded, so the cache never holds anything and the server is
| asked again for every single request.
|
*/

/**
 * The directory the file store is pointed at for the duration of a test.
 */
function cacheOnDisk(): string
{
    return storage_path('framework/testing/disk-cache');
}

beforeEach(function (): void {
    $path = cacheOnDisk();

    File::deleteDirectory($path);

    config([
        'cache.default' => 'file',
        'cache.stores.file.path' => $path,
    ]);

    // The store reads its configuration when it is built, so the one already
    // resolved under the default of the suite has to be dropped first.
    Cache::purge();
});

afterEach(function (): void {
    Cache::purge();

    File::deleteDirectory(cacheOnDisk());
});

it('reads a track from the cache instead of asking the server again', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $this->get('/audio/sg-1')->assertOk();
    $this->get('/audio/sg-1')->assertOk();

    expect(SubsonicApi::callsTo('getSong'))->toHaveCount(1);
});

it('reads the album list from the cache instead of asking the server again', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $this->get('/')->assertOk()->assertSee('Album di prova');
    $this->get('/')->assertOk()->assertSee('Album di prova');

    expect(SubsonicApi::callsTo('getAlbumList2'))->toHaveCount(1);
});

it('reads the playlist list from the cache instead of asking the server again', function (): void {
    SubsonicApi::fake(playlists: [SubsonicApi::playlist(SubsonicApi::songs(1))]);

    $this->get('/')->assertOk()->assertSee('Playlist di prova');
    $this->get('/')->assertOk()->assertSee('Playlist di prova');

    expect(SubsonicApi::callsTo('getPlaylists'))->toHaveCount(1);
});

it('keeps what it caches usable as the objects it stored', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $this->get('/audio/sg-1')->assertOk();

    $cached = Cache::get('navidrome:song:sg-1');

    expect($cached)->toBeInstanceOf(Song::class);
});
