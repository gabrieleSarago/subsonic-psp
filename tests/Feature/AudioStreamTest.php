<?php

use Illuminate\Support\Facades\Cache;
use Tests\Support\SubsonicApi;

it('streams an MP3 source untouched, with no transcode parameters', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song(['suffix' => 'mp3'])])]);

    $response = $this->get('/audio/sg-1');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'audio/mpeg');

    $calls = SubsonicApi::callsTo('stream');

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['id'])->toBe('sg-1')
        ->and($calls[0]['format'])->toBe('raw')
        ->and($calls[0])->not->toHaveKey('maxBitRate');
});

it('asks Navidrome to transcode a source the device cannot play', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song(['suffix' => 'flac'])])]);

    $this->get('/audio/sg-1')->assertOk();

    $calls = SubsonicApi::callsTo('stream');

    expect($calls[0]['format'])->toBe('mp3')
        ->and($calls[0]['maxBitRate'])->toBe('128');
});

it('uses the configured bitrate for the transcode', function (): void {
    config(['navidrome.psp_bitrate' => 96]);
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song(['suffix' => 'opus'])])]);

    $this->get('/audio/sg-1')->assertOk();

    expect(SubsonicApi::callsTo('stream')[0]['maxBitRate'])->toBe('96');
});

it('passes the bytes through without buffering them into the response', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song()])]);
    SubsonicApi::$audioBody = str_repeat('A', 200000);

    $response = $this->get('/audio/sg-1');

    expect(strlen($response->streamedContent()))->toBe(200000);
});

it('forwards a range request and relays the partial answer', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song()])]);
    SubsonicApi::$audioStatus = 206;
    SubsonicApi::$audioHeaders = ['Accept-Ranges' => 'bytes', 'Content-Range' => 'bytes 1000-1999/5000000'];
    SubsonicApi::$audioBody = '';

    $response = $this->get('/audio/sg-1', ['Range' => 'bytes=1000-1999']);

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 1000-1999/5000000');
    $response->assertHeader('Accept-Ranges', 'bytes');
});

it('relays an upstream answer that offers no range support', function (): void {
    // Navidrome answers a track it is transcoding for the first time without a
    // content length and without range support. The proxy must not invent one.
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song(['suffix' => 'flac'])])]);
    SubsonicApi::$audioStatus = 200;
    SubsonicApi::$audioHeaders = [];

    $response = $this->get('/audio/sg-1');

    $response->assertOk();
    expect($response->headers->has('Accept-Ranges'))->toBeFalse()
        ->and($response->headers->has('Content-Length'))->toBeFalse();
});

it('relays the content length of a source served untouched', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song()])]);
    SubsonicApi::$audioHeaders = ['Accept-Ranges' => 'bytes', 'Content-Length' => '1000000'];

    $this->get('/audio/sg-1')->assertHeader('Content-Length', '1000000');
});

it('answers 404 for a track that does not exist', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album(SubsonicApi::songs(1))]);

    $this->get('/audio/inesistente')->assertNotFound();
});

it('refuses to play an API error as if it were audio', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song()])]);
    SubsonicApi::$audioContentType = 'application/xml';
    SubsonicApi::$audioBody = '<subsonic-response status="failed"><error code="40" message="Wrong username or password"/></subsonic-response>';

    $this->get('/audio/sg-1')->assertStatus(502);
});

it('answers 503 when the connection drops while a track is being opened', function (): void {
    // The details of the track are served from cache while Navidrome has gone
    // away, so the fault reaches the device on the request that plays the audio
    // itself rather than on the one that reads its metadata.
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song()])]);
    SubsonicApi::$audioConnectionLost = true;

    $this->get('/audio/sg-1')->assertStatus(503);
});

it('changes the enclosure URL when the file behind a track changes', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song(['id' => 'sg-1', 'size' => 1000])])]);
    $first = feedEnclosures($this->get('/feeds/albums/al-1'))[0]['url'];

    // Replacing the file also replaces what the album endpoint reports, so the
    // cached copy has to go with it.
    SubsonicApi::serve(albums: [SubsonicApi::album([SubsonicApi::song(['id' => 'sg-1', 'size' => 2000])])]);
    Cache::flush();

    $second = feedEnclosures($this->get('/feeds/albums/al-1'))[0]['url'];

    expect($first)->not->toBe($second);
});

it('estimates the size of a transcode from the duration and the bitrate', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([
        SubsonicApi::song(['suffix' => 'flac', 'size' => 40_000_000, 'duration' => 60]),
    ])]);

    // 60 seconds at 128 kbps is 960000 bytes.
    expect(feedEnclosures($this->get('/feeds/albums/al-1'))[0]['length'])->toBe('960000');
});
