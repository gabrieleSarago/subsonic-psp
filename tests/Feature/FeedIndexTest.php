<?php

use Illuminate\Support\Facades\Cache;
use Tests\Support\Radios;
use Tests\Support\SubsonicApi;

it('lists every album and playlist found on the server', function (): void {
    SubsonicApi::fake(
        albums: [
            SubsonicApi::album(SubsonicApi::songs(3), ['id' => 'al-1', 'name' => 'Buongiorno', 'artist' => 'Zeta']),
            SubsonicApi::album(SubsonicApi::songs(2), ['id' => 'al-2', 'name' => 'Arrivederci', 'artist' => 'Alfa']),
        ],
        playlists: [SubsonicApi::playlist(SubsonicApi::songs(4), ['id' => 'pl-1', 'name' => 'Da ascoltare'])],
    );

    $response = $this->get('/');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('Buongiorno')
        ->toContain('Arrivederci')
        ->toContain('Da ascoltare')
        ->toContain('2 album')
        ->toContain('1 playlist');
});

it('links each entry to its feed', function (): void {
    SubsonicApi::fake(
        albums: [SubsonicApi::album(SubsonicApi::songs(1))],
        playlists: [SubsonicApi::playlist(SubsonicApi::songs(1))],
    );

    $response = $this->get('/');

    expect($response->getContent())
        ->toContain('href="'.route('feeds.albums.show', ['album' => 'al-1']).'"')
        ->toContain('href="'.route('feeds.playlists.show', ['playlist' => 'pl-1']).'"');
});

it('adds a link per part for a collection longer than a feed holds', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(250))]);

    $response = $this->get('/');

    expect($response->getContent())
        ->toContain(route('feeds.albums.part', ['album' => 'al-1', 'part' => 2]))
        ->toContain(route('feeds.albums.part', ['album' => 'al-1', 'part' => 3]));
});

it('offers no part links for a collection that fits in one feed', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(30))]);

    expect($this->get('/')->getContent())->not->toContain('/part/2');
});

it('files albums under the first letter of the artist and offers anchors', function (): void {
    SubsonicApi::fake(albums: [
        SubsonicApi::album(SubsonicApi::songs(1), ['id' => 'al-1', 'artist' => 'Alfa']),
        SubsonicApi::album(SubsonicApi::songs(1), ['id' => 'al-2', 'artist' => 'Beta']),
        SubsonicApi::album(SubsonicApi::songs(1), ['id' => 'al-3', 'artist' => 'Alfa']),
    ]);

    $content = (string) $this->get('/')->getContent();

    expect($content)
        ->toContain('href="#lettera-A"')
        ->toContain('href="#lettera-B"')
        ->toContain('id="lettera-A"');
});

it('shows a playlist created on the server on the next page load', function (): void {
    SubsonicApi::fake(playlists: [SubsonicApi::playlist(SubsonicApi::songs(1), ['name' => 'Prima'])]);
    expect($this->get('/')->getContent())->toContain('Prima')->not->toContain('Seconda');

    // The lists live in the cache, so a change on the server is only visible
    // after it expires or after navidrome:sync drops it.
    SubsonicApi::serve(playlists: [
        SubsonicApi::playlist(SubsonicApi::songs(1), ['id' => 'pl-1', 'name' => 'Prima']),
        SubsonicApi::playlist(SubsonicApi::songs(1), ['id' => 'pl-2', 'name' => 'Seconda']),
    ]);
    $this->artisan('navidrome:sync')->assertSuccessful();

    expect($this->get('/')->getContent())->toContain('Prima')->toContain('Seconda');
});

it('renders without any JavaScript', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $content = (string) $this->get('/')->getContent();

    expect($content)->not->toContain('<script')->not->toContain('javascript:');
});

it('recovers from a cached list written before the classes changed shape', function (): void {
    SubsonicApi::fake(
        albums: [SubsonicApi::album(SubsonicApi::songs(1))],
        playlists: [SubsonicApi::playlist(SubsonicApi::songs(1))],
    );

    // The cache holds objects. A copy written by an older version of them comes
    // back as something unreadable, and the page must still be served rather
    // than fail with a type error on the first request after an upgrade.
    Cache::put('navidrome:albums:index', 'non è una collezione', 300);
    Cache::put('navidrome:playlists:index', ['non' => 'un elenco'], 300);

    $content = (string) $this->get('/')->getContent();

    expect($content)->toContain('Album di prova')->toContain('Playlist di prova');
});

it('says so when the library is empty', function (): void {
    SubsonicApi::fake();

    $content = (string) $this->get('/')->getContent();

    expect($content)->toContain('Nessun album.')->toContain('Nessuna playlist.');
});

it('offers the radio feed as a single entry at the top', function (): void {
    SubsonicApi::fake();
    Radios::file([
        ['id' => 'rmc', 'name' => 'RMC Radio Monte Carlo', 'streamUrl' => 'http://stream.test/rmc', 'tagline' => 'Musica e notizie'],
        ['id' => 'kiss', 'name' => 'Radio Kiss Kiss', 'streamUrl' => 'http://stream.test/kiss'],
    ]);

    $content = (string) $this->get('/')->getContent();

    expect($content)
        ->toContain('href="'.route('feeds.radios.show').'"')
        ->toContain('2 stazioni')
        ->toContain('2 radio')
        ->not->toContain('RMC Radio Monte Carlo')
        ->not->toContain('Radio Kiss Kiss')
        ->not->toContain('Musica e notizie')
        ->not->toContain(route('radios.icon.show', ['station' => 'rmc']));

    expect(strpos($content, '<h2>Radio</h2>'))->toBeLessThan(strpos($content, '<h2>Playlist</h2>'));
});
