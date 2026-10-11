<?php

use Tests\Support\SubsonicApi;

it('proxies the artwork of an album', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $response = $this->get('/covers/al-1/100');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');
    expect($response->getContent())->toBe(SubsonicApi::COVER_BYTES);
});

it('asks the server for the requested size', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $this->get('/covers/al-1/240')->assertOk();

    expect(SubsonicApi::callsTo('getCoverArt')[0])
        ->toMatchArray(['id' => 'al-1', 'size' => '240']);
});

it('uses a default size when none is given', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $this->get('/covers/al-1')->assertOk();

    expect(SubsonicApi::callsTo('getCoverArt')[0]['size'])->toBe('100');
});

it('answers a neutral image rather than an error when the artwork is missing', function (): void {
    // The index page has no artwork field for a playlist, and an album can lose
    // its cover while its tracks stay playable.
    SubsonicApi::fake();

    $response = $this->get('/covers/inesistente/100');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');
});

it('serves the artwork as a JPEG thumbnail for the feed items', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $response = $this->get('/covers/al-1/thumb.jpg');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');

    [$width, $height] = getimagesizefromstring((string) $response->getContent());

    expect($width)->toBeLessThanOrEqual(160)
        ->and($height)->toBeLessThanOrEqual(120);
});

it('answers 404 for a missing cover thumbnail so the PSP draws its own icon', function (): void {
    SubsonicApi::fake();

    $this->get('/covers/al-1/thumb.jpg')->assertNotFound();
});

it('rejects a size outside the supported range', function (): void {
    SubsonicApi::fake();

    $this->get('/covers/al-1/4000')->assertNotFound();
});

it('links the index page to proxied artwork only', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    expect((string) $this->get('/')->getContent())
        ->toContain(route('covers.show.sized', ['cover' => 'al-1', 'size' => 100]))
        ->not->toContain('navidrome.test');
});

it('files an artist under the first letter of their name, ignoring a leading space', function (): void {
    // A name starting with a space would otherwise take every album of that
    // artist under one blank heading, and the page would look unsorted.
    SubsonicApi::fake(albums: [
        SubsonicApi::album(SubsonicApi::songs(1), ['id' => 'al-1', 'artist' => ' Beatles']),
        SubsonicApi::album(SubsonicApi::songs(1), ['id' => 'al-2', 'artist' => 'Zeta']),
    ]);

    $content = (string) $this->get('/')->getContent();

    expect($content)
        ->toContain('id="lettera-B"')
        ->toContain('id="lettera-Z"')
        ->not->toContain('id="lettera-%20"');
});
