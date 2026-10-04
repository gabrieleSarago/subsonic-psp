<?php

use Tests\Support\SubsonicApi;

it('serves an album feed with an enclosure per track', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(3))]);

    $response = $this->get('/feeds/albums/al-1');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    expect(feedItems($response))->toHaveCount(3)
        ->and((string) feedChannel($response)->title)->toBe('Artista di prova - Album di prova');

    $enclosures = feedEnclosures($response);

    // The fingerprint in the query makes the device download the track again
    // when the file behind it is replaced.
    expect($enclosures[0]['url'])->toMatch('#^http://localhost:8000/audio/sg-1\?v=[a-f0-9]{12}$#')
        ->and($enclosures[0]['length'])->toBe('1000000')
        ->and($enclosures[0]['type'])->toBe('audio/mpeg');
});

it('never puts the Navidrome credentials in the feed', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $body = (string) $this->get('/feeds/albums/al-1')->getContent();

    expect($body)
        ->not->toContain('s3cret')
        ->not->toContain('navidrome.test')
        ->not->toContain('t=');
});

it('writes dates as RFC 822 in GMT', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $response = $this->get('/feeds/albums/al-1');

    expect((string) feedItems($response)[0]->pubDate)->toBe('Thu, 15 Jan 2026 10:00:00 GMT')
        ->and((string) feedChannel($response)->lastBuildDate)->toBe('Thu, 15 Jan 2026 10:00:00 GMT');
});

it('answers 404 for an album that does not exist', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $this->get('/feeds/albums/inesistente')->assertNotFound();
});

it('answers 404 for an album without tracks, rather than failing', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album()]);

    $response = $this->get('/feeds/albums/al-1');

    $response->assertOk();
    expect(feedItems($response))->toBeEmpty()
        ->and((string) feedChannel($response)->description)->toBe('Nessun brano disponibile');
});

it('splits a long album into parts of one hundred items', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(250))]);

    expect(feedItems($this->get('/feeds/albums/al-1')))->toHaveCount(100)
        ->and(feedItems($this->get('/feeds/albums/al-1/part/2')))->toHaveCount(100)
        ->and(feedItems($this->get('/feeds/albums/al-1/part/3')))->toHaveCount(50);
});

it('keeps the tracks of each part in order and without overlap', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(250))]);

    $first = (string) feedItems($this->get('/feeds/albums/al-1'))[0]->guid;
    $lastOfFirstPart = (string) feedItems($this->get('/feeds/albums/al-1'))[99]->guid;
    $firstOfSecondPart = (string) feedItems($this->get('/feeds/albums/al-1/part/2'))[0]->guid;
    $lastOfLastPart = (string) feedItems($this->get('/feeds/albums/al-1/part/3'))[49]->guid;

    expect($first)->toBe('sg-1')
        ->and($lastOfFirstPart)->toBe('sg-100')
        ->and($firstOfSecondPart)->toBe('sg-101')
        ->and($lastOfLastPart)->toBe('sg-250');
});

it('names the part in the channel title only when there is more than one', function (): void {
    SubsonicApi::fake(
        albums: [SubsonicApi::album(SubsonicApi::songs(250))],
        playlists: [SubsonicApi::playlist(SubsonicApi::songs(3))],
    );

    expect((string) feedChannel($this->get('/feeds/albums/al-1/part/2'))->title)
        ->toBe('Artista di prova - Album di prova (parte 2 di 3)')
        ->and((string) feedChannel($this->get('/feeds/albums/al-1'))->title)
        ->toBe('Artista di prova - Album di prova (parte 1 di 3)')
        ->and((string) feedChannel($this->get('/feeds/playlists/pl-1'))->title)
        ->toBe('Playlist di prova');
});

it('describes the range each part covers', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(250))]);

    expect((string) feedChannel($this->get('/feeds/albums/al-1/part/2'))->description)
        ->toBe('tracce 101-200 di 250')
        ->and((string) feedChannel($this->get('/feeds/albums/al-1/part/3'))->description)
        ->toBe('tracce 201-250 di 250');
});

it('answers 404 for a part beyond the last one', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(250))]);

    $this->get('/feeds/albums/al-1/part/4')->assertNotFound();
});

it('answers 404 for a part of an album that is not split', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(3))]);

    $this->get('/feeds/albums/al-1/part/2')->assertNotFound();
});

it('rejects a part number that is not a number', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(3))]);

    $this->get('/feeds/albums/al-1/part/second')->assertNotFound();
});
