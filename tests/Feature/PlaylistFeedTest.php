<?php

use Tests\Support\SubsonicApi;

it('serves a playlist feed with an enclosure per entry', function (): void {
    SubsonicApi::fake(playlists: [SubsonicApi::playlist(SubsonicApi::songs(2))]);

    $response = $this->get('/feeds/playlists/pl-1');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    expect(feedItems($response))->toHaveCount(2)
        ->and((string) feedChannel($response)->title)->toBe('Playlist di prova')
        ->and(feedEnclosures($response)[1]['url'])->toMatch('#^http://localhost:8000/audio/sg-2\?v=[a-f0-9]{12}$#');
});

it('drops entries that point at a file which is no longer there', function (): void {
    $playlist = SubsonicApi::playlist(SubsonicApi::songs(1));
    $playlist['entry'][] = ['id' => 999];

    SubsonicApi::fake(playlists: [$playlist]);

    expect(feedItems($this->get('/feeds/playlists/pl-1')))->toHaveCount(1);
});

it('answers 404 for a playlist that does not exist', function (): void {
    SubsonicApi::fake(playlists: [SubsonicApi::playlist(SubsonicApi::songs(1))]);

    $this->get('/feeds/playlists/inesistente')->assertNotFound();
});

it('splits a long playlist into parts', function (): void {
    SubsonicApi::fake(playlists: [SubsonicApi::playlist(SubsonicApi::songs(120))]);

    expect(feedItems($this->get('/feeds/playlists/pl-1')))->toHaveCount(100)
        ->and(feedItems($this->get('/feeds/playlists/pl-1/part/2')))->toHaveCount(20)
        ->and((string) feedChannel($this->get('/feeds/playlists/pl-1/part/2'))->title)
        ->toBe('Playlist di prova (parte 2 di 2)');
});

it('shows a playlist created on the server without a restart', function (): void {
    SubsonicApi::fake(playlists: [SubsonicApi::playlist(SubsonicApi::songs(1), ['id' => 'pl-1'])]);
    expect(feedItems($this->get('/feeds/playlists/pl-1')))->toHaveCount(1);

    SubsonicApi::serve(playlists: [
        SubsonicApi::playlist(SubsonicApi::songs(1), ['id' => 'pl-1']),
        SubsonicApi::playlist(SubsonicApi::songs(2), ['id' => 'pl-2', 'name' => 'Playlist nuova']),
    ]);

    expect(feedItems($this->get('/feeds/playlists/pl-2')))->toHaveCount(2);
});
